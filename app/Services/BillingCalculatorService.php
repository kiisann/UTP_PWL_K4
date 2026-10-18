<?php

namespace App\Services;

use App\Models\RentalSession;
use Carbon\CarbonInterface;

class BillingCalculatorService
{
    /**
     * @return array{actual_minutes:int, effective_minutes:int, billed_minutes:int, rental_amount:float}
     */
    public function calculateOpenBill(
        CarbonInterface $start,
        CarbonInterface $end,
        float $hourlyRate,
        int $minChargeMinutes,
        int $roundingMinutes
    ): array {
        $roundingMinutes = max(1, $roundingMinutes);

        $seconds = max(0, $end->getTimestamp() - $start->getTimestamp());
        $actualMinutes = (int) ceil($seconds / 60);

        $effectiveMinutes = max($actualMinutes, $minChargeMinutes);

        $billedMinutes = (int) (ceil($effectiveMinutes / $roundingMinutes) * $roundingMinutes);

        $rentalAmount = round(($billedMinutes / 60) * $hourlyRate, 2);

        return [
            'actual_minutes' => $actualMinutes,
            'effective_minutes' => $effectiveMinutes,
            'billed_minutes' => $billedMinutes,
            'rental_amount' => $rentalAmount,
        ];
    }

    public function grandTotal(float $rentalAmount, float $fnbAmount = 0, float $discountAmount = 0): float
    {
        return max(0.0, round($rentalAmount + $fnbAmount - $discountAmount, 2));
    }

    /**
     * Tagihan sementara sebuah sesi (dipakai kartu meja, live status, dan pratinjau struk).
     *
     * - Open Bill yang masih berjalan dihitung sampai $at (default: sekarang) memakai rumus 4 langkah.
     * - Selain itu (Paket, atau Open Bill yang sudah di-Stop) memakai nilai yang tersimpan.
     * grand_total selalu dihitung ulang dari sewa + F&B - diskon, jadi ikut berubah saat F&B bertambah.
     *
     * @return array{rental_amount:float, total_minutes:int, grand_total:float, is_live:bool}
     */
    public function snapshot(RentalSession $session, ?CarbonInterface $at = null): array
    {
        $isLive = $session->billing_mode === RentalSession::MODE_OPEN && $session->actual_end_time === null;

        if ($isLive) {
            $r = $this->forSession($session, $at);
            $rental = $r['rental_amount'];
            $minutes = $r['total_minutes'];
        } else {
            $rental = (float) $session->rental_amount;
            $minutes = (int) $session->total_minutes;
        }

        return [
            'rental_amount' => $rental,
            'total_minutes' => $minutes,
            'grand_total' => $this->grandTotal($rental, (float) $session->fnb_amount, (float) $session->discount_amount),
            'is_live' => $isLive,
        ];
    }

    /**
     * @return array{actual_minutes:int, effective_minutes:int, billed_minutes:int, rental_amount:float, total_minutes:int, grand_total:float}
     */
    public function forSession(RentalSession $session, ?CarbonInterface $end = null): array
    {
        $session->loadMissing('console.category');
        $category = $session->console->category;

        $result = $this->calculateOpenBill(
            $session->start_time,
            $end ?? now(),
            (float) $category->hourly_rate,
            (int) $category->min_charge_minutes,
            (int) $category->rounding_minutes
        );

        $result['total_minutes'] = $result['billed_minutes'];
        $result['grand_total'] = $this->grandTotal(
            $result['rental_amount'],
            (float) $session->fnb_amount,
            (float) $session->discount_amount
        );

        return $result;
    }
}