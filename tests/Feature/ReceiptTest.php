<?php

namespace Tests\Feature;

use App\Models\FnbOrder;
use App\Models\FnbOrderItem;
use App\Models\Payment;
use App\Models\RentalSession;
use App\Services\SessionManagerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsRentalData;
use Tests\TestCase;

class ReceiptTest extends TestCase
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

    private function sesi(string $mode = 'package'): RentalSession
    {
        $u = $this->makeCashier();
        $this->openShift($u);
        $cat = $this->makeCategory(10000);
        $console = $this->makeConsole($cat, 'A');
        $pkg = $this->makePackage($cat, 180, 25000);

        return app(SessionManagerService::class)->start(
            $u, $console->id, $mode, $mode === 'package' ? $pkg->id : null, 'Budi'
        );
    }

    private function tambahFnb(RentalSession $s, int $qty, float $price): void
    {
        $product = $this->makeProduct(10, $price);
        $order = FnbOrder::create([
            'order_number' => 'FNB-' . uniqid(),
            'rental_session_id' => $s->id,
            'shift_id' => $s->shift_id,
            'total_amount' => $qty * $price,
            'order_status' => 'open',
        ]);
        FnbOrderItem::create([
            'fnb_order_id' => $order->id,
            'product_id' => $product->id,
            'qty' => $qty,
            'unit_price' => $price,
            'subtotal' => $qty * $price,
        ]);
        $s->update(['fnb_amount' => $s->fnb_amount + $qty * $price]);
    }

    public function test_struk_lunas_memuat_semua_bagian_sesuai_dokumen(): void
    {
        $s = $this->sesi('package');
        $this->tambahFnb($s, 2, 5000);
        $s->update([
            'payment_status' => RentalSession::PAID,
            'session_status' => RentalSession::COMPLETED,
            'actual_end_time' => now()->addMinutes(180),
            'grand_total' => 35000,
        ]);
        Payment::create([
            'rental_session_id' => $s->id,
            'shift_id' => $s->shift_id,
            'payment_method' => 'cash',
            'bill_amount' => 35000,
            'paid_amount' => 50000,
            'change_amount' => 15000,
            'paid_at' => now(),
        ]);

        $res = $this->get(route('sessions.receipt', $s));

        $res->assertOk()
            ->assertSee('PlayZone Test')
            ->assertSee('Jl. Uji 1')
            ->assertSee('INV-20261015-0001')
            ->assertSee('Budi')
            ->assertSee('Meja A')
            ->assertSee('Paket 180 menit')
            ->assertSee('13:00 - 16:00')
            ->assertSee('Mi Instan')
            ->assertSee('2 x 5.000')
            ->assertSee('Rp 35.000')
            ->assertSee('TUNAI')
            ->assertSee('Rp 50.000')
            ->assertSee('Rp 15.000')
            ->assertDontSee('BELUM LUNAS')
            ->assertDontSee('DIBATALKAN');
    }

    public function test_sesi_belum_dibayar_tampil_sebagai_pratinjau(): void
    {
        $s = $this->sesi('package');

        $this->get(route('sessions.receipt', $s))
            ->assertOk()
            ->assertSee('PRATINJAU - BELUM LUNAS');
    }

    public function test_pratinjau_open_bill_berjalan_dihitung_sampai_sekarang(): void
    {
        $s = $this->sesi('open');
        Carbon::setTestNow('2026-10-15 13:40:00'); // 40 menit -> blok 45 menit -> 7.500

        $this->get(route('sessions.receipt', $s))
            ->assertOk()
            ->assertSee('Open Bill')
            ->assertSee('40 menit')
            ->assertSee('45 menit')
            ->assertSee('Rp 7.500');
    }

    public function test_sesi_void_ditandai_dibatalkan(): void
    {
        $s = $this->sesi('open');
        app(SessionManagerService::class)->void($s->fresh(), 'Batal');

        $this->get(route('sessions.receipt', $s))
            ->assertOk()
            ->assertSee('DIBATALKAN');
    }

    public function test_lebar_kertas_bisa_dipilih_dan_nilai_aneh_kembali_ke_58(): void
    {
        $s = $this->sesi('open');

        $this->get(route('sessions.receipt', [$s, 'width' => 80]))->assertSee('size: 80mm', false);
        $this->get(route('sessions.receipt', [$s, 'width' => 123]))->assertSee('size: 58mm', false);
    }

    public function test_parameter_print_menyertakan_dialog_cetak_otomatis(): void
    {
        $s = $this->sesi('open');

        $this->get(route('sessions.receipt', $s))->assertDontSee('window.print();', false);
        $this->get(route('sessions.receipt', [$s, 'print' => 1]))->assertSee('window.print();', false);
    }
}
