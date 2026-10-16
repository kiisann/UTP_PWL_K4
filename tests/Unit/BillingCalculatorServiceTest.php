<?php

namespace Tests\Unit;

use App\Services\BillingCalculatorService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class BillingCalculatorServiceTest extends TestCase
{
    private BillingCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BillingCalculatorService();
    }

    private function hitung(string $mulai, string $selesai): array
    {
        return $this->service->calculateOpenBill(
            Carbon::parse($mulai),
            Carbon::parse($selesai),
            10000,
            30,
            15
        );
    }

    public function test_main_10_menit_kena_minimum_30_menit(): void
    {
        $r = $this->hitung('2026-10-15 13:00:00', '2026-10-15 13:10:00');

        $this->assertSame(10, $r['actual_minutes']);
        $this->assertSame(30, $r['effective_minutes']);
        $this->assertSame(30, $r['billed_minutes']);
        $this->assertEquals(5000, $r['rental_amount']);
    }

    public function test_31_menit_dibulatkan_ke_blok_45_menit(): void
    {
        $r = $this->hitung('2026-10-15 13:00:00', '2026-10-15 13:31:00');

        $this->assertSame(31, $r['actual_minutes']);
        $this->assertSame(45, $r['billed_minutes']);
        $this->assertEquals(7500, $r['rental_amount']);
    }

    public function test_tepat_45_menit_tidak_naik_blok(): void
    {
        $r = $this->hitung('2026-10-15 13:00:00', '2026-10-15 13:45:00');

        $this->assertSame(45, $r['billed_minutes']);
        $this->assertEquals(7500, $r['rental_amount']);
    }

    public function test_detik_sisa_dibulatkan_ke_atas(): void
    {
        $r = $this->hitung('2026-10-15 13:00:00', '2026-10-15 13:59:01');

        $this->assertSame(60, $r['actual_minutes']);
        $this->assertSame(60, $r['billed_minutes']);
        $this->assertEquals(10000, $r['rental_amount']);
    }

    public function test_1_jam_1_menit_1_detik_jadi_75_menit(): void
    {
        $r = $this->hitung('2026-10-15 13:00:00', '2026-10-15 14:01:01');

        $this->assertSame(62, $r['actual_minutes']);
        $this->assertSame(75, $r['billed_minutes']);
        $this->assertEquals(12500, $r['rental_amount']);
    }

    public function test_sesi_melewati_tengah_malam(): void
    {
        $r = $this->hitung('2026-10-15 23:50:00', '2026-10-16 00:20:00');

        $this->assertSame(30, $r['actual_minutes']);
        $this->assertSame(30, $r['billed_minutes']);
        $this->assertEquals(5000, $r['rental_amount']);
    }

    public function test_waktu_selesai_sebelum_mulai_dianggap_nol_lalu_kena_minimum(): void
    {
        $r = $this->hitung('2026-10-15 13:00:00', '2026-10-15 12:00:00');

        $this->assertSame(0, $r['actual_minutes']);
        $this->assertSame(30, $r['billed_minutes']);
        $this->assertEquals(5000, $r['rental_amount']);
    }

    public function test_grand_total_sewa_plus_fnb_dikurangi_diskon(): void
    {
        $this->assertEquals(17500, $this->service->grandTotal(7500, 12000, 2000));

        $this->assertEquals(0, $this->service->grandTotal(5000, 0, 9000));
    }
}