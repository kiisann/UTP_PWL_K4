<?php

namespace App\Services;

use App\Models\RentalSession;
use Carbon\Carbon;

class BillingCalculatorService
{
    public function forSession(RentalSession $session): array
    {
        
        if (
            $session->billing_mode === RentalSession::MODE_PACKAGE
            && $session->package
        ) {
            $rentalAmount = (float) $session->package->price;
            $minutes = (int) $session->package->duration_minutes;
        } else {
            // Hitung durasi sesi reguler.
            $start = Carbon::parse($session->start_time);
            $end = $session->actual_end_time
                ? Carbon::parse($session->actual_end_time)
                : now();

            $minutes = max(0, (int) ceil($start->diffInSeconds($end) / 60));

            $category = $session->console?->category;

            if (!$category) {
                throw new \RuntimeException(
                    'Kategori console tidak ditemukan.'
                );
            }

            
            $minutes = max(
                $minutes,
                (int) $category->min_charge_minutes
            );

            $rounding = max(1, (int) $category->rounding_minutes);

            $minutes = (int) (ceil($minutes / $rounding) * $rounding);

            
            $hourlyRate = (float) $category->hourly_rate;
            $rentalAmount = round(($minutes / 60) * $hourlyRate, 2);
        }

        $fnbAmount = (float) $session->fnb_amount;
        $discountAmount = (float) $session->discount_amount;

        return [
            'minutes' => $minutes,
            'rental_amount' => $rentalAmount,
            'fnb_amount' => $fnbAmount,
            'discount_amount' => $discountAmount,
            'grand_total' => max(
                0,
                $rentalAmount + $fnbAmount - $discountAmount
            ),
        ];
    }
}