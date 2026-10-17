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