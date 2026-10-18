<?php

namespace Tests\Feature;

use App\Models\FnbOrder;
use App\Models\Payment;
use App\Models\RentalSession;
use App\Services\SessionManagerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsRentalData;
use Tests\TestCase;

class FnbOrderTest extends TestCase
{
    use RefreshDatabase, BuildsRentalData;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 13:00:00');
        config(['rental.name' => 'PlayZone Test', 'rental.address' => 'Jl. Uji 1', 'rental.phone' => '0812']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_kasir_bisa_menambah_fnb_ke_sesi_rental(): void
    {
        $cashier = $this->makeCashier();
        $this->openShift($cashier, 100000);
        $cat = $this->makeCategory(10000);
        $console = $this->makeConsole($cat, 'A1');
        $pkg = $this->makePackage($cat, 60, 10000);
        $product = $this->makeProduct(10, 5000);

        $session = app(SessionManagerService::class)->start(
            $cashier, $console->id, 'package', $pkg->id, 'Andi'
        );

        $this->assertEquals(0, $session->fnb_amount);
        $this->assertEquals(10000, $session->grand_total);

        $response = $this->actingAs($cashier)->post(route('sessions.fnb.store', $session), [
            'items' => [
                ['product_id' => $product->id, 'qty' => 2],
            ],
        ]);

        $response->assertSessionHas('status');

        // Stok berkurang 2
        $this->assertEquals(8, $product->fresh()->stock);

        // Nilai F&B dan Grand Total sesi terupdate
        $session->refresh();
        $this->assertEquals(10000, $session->fnb_amount);
        $this->assertEquals(20000, $session->grand_total);

        // FnbOrder terbentuk
        $order = FnbOrder::where('rental_session_id', $session->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(10000, $order->total_amount);
        $this->assertCount(1, $order->items);
        $this->assertEquals(2, $order->items->first()->qty);
    }

    public function test_menambah_fnb_ke_sesi_gagal_jika_stok_tidak_cukup(): void
    {
        $cashier = $this->makeCashier();
        $this->openShift($cashier, 100000);
        $cat = $this->makeCategory(10000);
        $console = $this->makeConsole($cat, 'A2');
        $pkg = $this->makePackage($cat, 60, 10000);
        $product = $this->makeProduct(2, 5000);

        $session = app(SessionManagerService::class)->start(
            $cashier, $console->id, 'package', $pkg->id, 'Budi'
        );

        $response = $this->actingAs($cashier)->post(route('sessions.fnb.store', $session), [
            'items' => [
                ['product_id' => $product->id, 'qty' => 5], // minta 5, stok hanya 2
            ],
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(2, $product->fresh()->stock);
        $this->assertEquals(0, $session->fresh()->fnb_amount);
    }

    public function test_menambah_fnb_ke_sesi_ditolak_jika_sesi_sudah_lunas(): void
    {
        $cashier = $this->makeCashier();
        $this->openShift($cashier, 100000);
        $cat = $this->makeCategory(10000);
        $console = $this->makeConsole($cat, 'A3');
        $pkg = $this->makePackage($cat, 60, 10000);
        $product = $this->makeProduct(10, 5000);

        $session = app(SessionManagerService::class)->start(
            $cashier, $console->id, 'package', $pkg->id, 'Citra'
        );

        $session->update([
            'payment_status' => RentalSession::PAID,
            'session_status' => RentalSession::COMPLETED,
        ]);

        $response = $this->actingAs($cashier)->post(route('sessions.fnb.store', $session), [
            'items' => [
                ['product_id' => $product->id, 'qty' => 1],
            ],
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(10, $product->fresh()->stock);
    }

    public function test_walk_in_fnb_berhasil_dengan_pembayaran_tunai(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->openShift($cashier, 100000);
        $product = $this->makeProduct(15, 6000);

        $response = $this->actingAs($cashier)->post(route('fnb-orders.walk-in'), [
            'customer_name' => 'Tamu Toko',
            'items' => [
                ['product_id' => $product->id, 'qty' => 3], // 3 x 6000 = 18000
            ],
            'payment_method' => 'cash',
            'paid_amount' => 20000,
        ]);

        $response->assertSessionHas('status');

        // Stok produk terpotong
        $this->assertEquals(12, $product->fresh()->stock);

        // FnbOrder tercipta dengan status paid
        $order = FnbOrder::whereNull('rental_session_id')->first();
        $this->assertNotNull($order);
        $this->assertEquals(18000, $order->total_amount);
        $this->assertEquals('paid', $order->order_status);

        // Payment record tercipta
        $payment = Payment::where('fnb_order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(18000, $payment->bill_amount);
        $this->assertEquals(20000, $payment->paid_amount);
        $this->assertEquals(2000, $payment->change_amount);
        $this->assertEquals('cash', $payment->payment_method);

        // Shift expected cash bertambah 18.000
        $shift->refresh();
        $this->assertEquals(118000, $shift->expected_cash);
    }

    public function test_walk_in_fnb_berhasil_dengan_pembayaran_qris(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->openShift($cashier, 100000);
        $product = $this->makeProduct(10, 8000);

        $response = $this->actingAs($cashier)->post(route('fnb-orders.walk-in'), [
            'customer_name' => 'Tamu QRIS',
            'items' => [
                ['product_id' => $product->id, 'qty' => 2], // 16.000
            ],
            'payment_method' => 'qris',
            'paid_amount' => 16000,
        ]);

        $response->assertSessionHas('status');

        $shift->refresh();
        $this->assertEquals(100000, $shift->expected_cash);
        $this->assertEquals(16000, $shift->qris_total);
    }

    public function test_walk_in_fnb_gagal_jika_uang_kurang(): void
    {
        $cashier = $this->makeCashier();
        $this->openShift($cashier, 100000);
        $product = $this->makeProduct(10, 10000);

        $response = $this->actingAs($cashier)->post(route('fnb-orders.walk-in'), [
            'customer_name' => 'Tamu Uang Kurang',
            'items' => [
                ['product_id' => $product->id, 'qty' => 2], // 20.000
            ],
            'payment_method' => 'cash',
            'paid_amount' => 15000, // kurang 5.000
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(10, $product->fresh()->stock);
    }

    public function test_walk_in_struk_dapat_dibuka_dan_memuat_rincian_pesanan(): void
    {
        $cashier = $this->makeCashier();
        $this->openShift($cashier, 100000);
        $product = $this->makeProduct(10, 5000);

        $this->actingAs($cashier)->post(route('fnb-orders.walk-in'), [
            'customer_name' => 'Doni Walkin',
            'items' => [
                ['product_id' => $product->id, 'qty' => 2],
            ],
            'payment_method' => 'cash',
            'paid_amount' => 10000,
        ]);

        $order = FnbOrder::whereNull('rental_session_id')->first();
        $this->assertNotNull($order);

        $receiptResponse = $this->actingAs($cashier)->get(route('fnb-orders.receipt', $order));

        $receiptResponse->assertOk()
            ->assertSee('PlayZone Test')
            ->assertSee('Doni Walkin')
            ->assertSee('STRUK PEMBELIAN F&B')
            ->assertSee('Mi Instan')
            ->assertSee('2 x 5.000')
            ->assertSee('Rp 10.000')
            ->assertSee('LUNAS');
    }
}
