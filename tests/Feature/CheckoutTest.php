<?php

namespace Tests\Feature;

use App\Models\Console;
use App\Models\Payment;
use App\Models\RentalSession;
use App\Services\SessionManagerService;
use App\Services\ShiftReconciliationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsRentalData;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase, BuildsRentalData;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 13:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_kasir_bisa_checkout_sesi_dengan_uang_tunai_pas(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->openShift($cashier, 100000);
        $cat = $this->makeCategory(10000);
        $console = $this->makeConsole($cat, 'A1');
        $pkg = $this->makePackage($cat, 60, 10000);

        $session = app(SessionManagerService::class)->start(
            $cashier, $console->id, 'package', $pkg->id, 'Andi'
        );

        $this->assertEquals(Console::OCCUPIED, $console->fresh()->status);
        $this->assertEquals(RentalSession::UNPAID, $session->payment_status);

        $response = $this->actingAs($cashier)->post(route('sessions.checkout', $session), [
            'payment_method' => 'cash',
            'paid_amount' => 10000,
        ]);

        $response->assertSessionHas('status');

        $session->refresh();
        $this->assertEquals(RentalSession::PAID, $session->payment_status);
        $this->assertEquals(RentalSession::COMPLETED, $session->session_status);
        $this->assertEquals(Console::AVAILABLE, $console->fresh()->status);

        $payment = Payment::where('rental_session_id', $session->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('cash', $payment->payment_method);
        $this->assertEquals(10000, $payment->bill_amount);
        $this->assertEquals(10000, $payment->paid_amount);
        $this->assertEquals(0, $payment->change_amount);

        $shift->refresh();
        $this->assertEquals(110000, $shift->expected_cash);
    }

    public function test_checkout_dengan_uang_tunai_lebih_menghitung_kembalian(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->openShift($cashier, 100000);
        $cat = $this->makeCategory(10000);
        $console = $this->makeConsole($cat, 'A2');
        $pkg = $this->makePackage($cat, 60, 10000);

        $session = app(SessionManagerService::class)->start(
            $cashier, $console->id, 'package', $pkg->id, 'Budi'
        );

        $response = $this->actingAs($cashier)->post(route('sessions.checkout', $session), [
            'payment_method' => 'cash',
            'paid_amount' => 50000,
        ]);

        $response->assertSessionHas('status');

        $payment = Payment::where('rental_session_id', $session->id)->first();
        $this->assertEquals(50000, $payment->paid_amount);
        $this->assertEquals(40000, $payment->change_amount);

        // Uang masuk laci kasir bertambah sebesar tagihan (bill_amount = 10000)
        $shift->refresh();
        $this->assertEquals(110000, $shift->expected_cash);
    }

    public function test_checkout_gagal_jika_uang_tunai_kurang(): void
    {
        $cashier = $this->makeCashier();
        $this->openShift($cashier, 100000);
        $cat = $this->makeCategory(10000);
        $console = $this->makeConsole($cat, 'A3');
        $pkg = $this->makePackage($cat, 60, 10000);

        $session = app(SessionManagerService::class)->start(
            $cashier, $console->id, 'package', $pkg->id, 'Citra'
        );

        $response = $this->actingAs($cashier)->post(route('sessions.checkout', $session), [
            'payment_method' => 'cash',
            'paid_amount' => 5000,
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(RentalSession::UNPAID, $session->fresh()->payment_status);
    }

    public function test_checkout_dengan_qris_memperbarui_total_qris_shift(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->openShift($cashier, 100000);
        $cat = $this->makeCategory(10000);
        $console = $this->makeConsole($cat, 'A4');
        $pkg = $this->makePackage($cat, 60, 15000);

        $session = app(SessionManagerService::class)->start(
            $cashier, $console->id, 'package', $pkg->id, 'Dedi'
        );

        $response = $this->actingAs($cashier)->post(route('sessions.checkout', $session), [
            'payment_method' => 'qris',
            'paid_amount' => 15000,
        ]);

        $response->assertSessionHas('status');

        $payment = Payment::where('rental_session_id', $session->id)->first();
        $this->assertEquals('qris', $payment->payment_method);
        $this->assertEquals(15000, $payment->bill_amount);
        $this->assertEquals(0, $payment->change_amount);

        $shift->refresh();
        $this->assertEquals(100000, $shift->expected_cash);
        $this->assertEquals(15000, $shift->qris_total);
    }

    public function test_checkout_menolak_sesi_yang_sudah_lunas(): void
    {
        $cashier = $this->makeCashier();
        $this->openShift($cashier, 100000);
        $cat = $this->makeCategory(10000);
        $console = $this->makeConsole($cat, 'A5');
        $pkg = $this->makePackage($cat, 60, 10000);

        $session = app(SessionManagerService::class)->start(
            $cashier, $console->id, 'package', $pkg->id, 'Eka'
        );

        // Checkout pertama kali
        $this->actingAs($cashier)->post(route('sessions.checkout', $session), [
            'payment_method' => 'cash',
            'paid_amount' => 10000,
        ]);

        // Checkout kedua kali
        $response = $this->actingAs($cashier)->post(route('sessions.checkout', $session), [
            'payment_method' => 'cash',
            'paid_amount' => 10000,
        ]);

        $response->assertSessionHas('error');
    }

    public function test_checkout_gagal_jika_tidak_ada_shift_aktif(): void
    {
        $cashier = $this->makeCashier();
        // Shift tidak dibuka
        $cat = $this->makeCategory(10000);
        $console = $this->makeConsole($cat, 'A6');
        $pkg = $this->makePackage($cat, 60, 10000);

        $shiftTemp = $this->openShift($cashier, 100000);
        $session = app(SessionManagerService::class)->start(
            $cashier, $console->id, 'package', $pkg->id, 'Fani'
        );
        // Tutup shift
        app(ShiftReconciliationService::class)->close($shiftTemp, 100000);

        $response = $this->actingAs($cashier)->post(route('sessions.checkout', $session), [
            'payment_method' => 'cash',
            'paid_amount' => 10000,
        ]);

        $response->assertSessionHas('error');
    }

    public function test_checkout_otomatis_menghentikan_sesi_yang_masih_berjalan(): void
    {
        $cashier = $this->makeCashier();
        $this->openShift($cashier, 100000);
        $cat = $this->makeCategory(10000);
        $console = $this->makeConsole($cat, 'A7');

        $session = app(SessionManagerService::class)->start(
            $cashier, $console->id, 'open', null, 'Gilang'
        );

        $this->assertNull($session->actual_end_time);

        Carbon::setTestNow('2026-10-15 13:45:00'); // 45 menit rental

        $response = $this->actingAs($cashier)->post(route('sessions.checkout', $session), [
            'payment_method' => 'cash',
            'paid_amount' => 10000,
        ]);

        $response->assertSessionHas('status');

        $session->refresh();
        $this->assertNotNull($session->actual_end_time);
        $this->assertEquals(RentalSession::COMPLETED, $session->session_status);
        $this->assertEquals(RentalSession::PAID, $session->payment_status);
    }
}
