<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Services\ShiftReconciliationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsRentalData;
use Tests\TestCase;

class ShiftReconciliationServiceTest extends TestCase
{
    use RefreshDatabase, BuildsRentalData;

    private ShiftReconciliationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ShiftReconciliationService::class);
    }

    private function bayar(int $shiftId, string $method, float $bill, float $paid, float $change = 0): void
    {
        Payment::create([
            'shift_id' => $shiftId,
            'payment_method' => $method,
            'bill_amount' => $bill,
            'paid_amount' => $paid,
            'change_amount' => $change,
            'paid_at' => now(),
        ]);
    }

    public function test_hanya_satu_shift_boleh_berjalan(): void
    {
        $user = $this->makeCashier();
        $this->svc->open($user, 100000);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('masih berjalan');
        $this->svc->open($this->makeCashier('kasir2'), 50000);
    }

    public function test_modal_awal_tidak_boleh_negatif(): void
    {
        $this->expectException(DomainException::class);
        $this->svc->open($this->makeCashier(), -1);
    }

    public function test_tutup_shift_memisahkan_tunai_dan_qris_serta_menghitung_selisih(): void
    {
        $shift = $this->svc->open($this->makeCashier(), 100000);

        $this->bayar($shift->id, 'cash', 23000, 50000, 27000); // masuk laci 23.000
        $this->bayar($shift->id, 'cash', 10000, 10000, 0);     // masuk laci 10.000
        $this->bayar($shift->id, 'qris', 40000, 40000, 0);     // tidak masuk laci

        $result = $this->svc->close($shift, 130000);

        $this->assertEquals(133000, $result['expected_cash']); // 100.000 + 23.000 + 10.000
        $this->assertEquals(40000, $result['qris_total']);
        $this->assertEquals(-3000, $result['difference']);     // fisik kurang 3.000
        $this->assertNotNull($result['shift']->closed_at);
    }

    public function test_pembayaran_shift_lain_tidak_ikut_terhitung(): void
    {
        $user = $this->makeCashier();
        $first = $this->svc->open($user, 50000);
        $this->bayar($first->id, 'cash', 20000, 20000);
        $this->svc->close($first, 70000);

        $second = $this->svc->open($user, 50000);

        $summary = $this->svc->summary($second);
        $this->assertEquals(0, $summary['cash_sales']);
        $this->assertEquals(50000, $summary['expected_cash']);
    }

    public function test_shift_yang_sudah_ditutup_tidak_bisa_ditutup_lagi(): void
    {
        $shift = $this->svc->open($this->makeCashier(), 0);
        $this->svc->close($shift, 0);

        $this->expectException(DomainException::class);
        $this->svc->close($shift, 0);
    }
}
