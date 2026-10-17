<?php

namespace Database\Seeders;

use App\Models\CashierShift;
use App\Models\Console;
use App\Models\PricingPackage;
use App\Models\RentalSession;
use App\Models\User;
use Illuminate\Database\Seeder;


class DemoTimerSeeder extends Seeder
{
    public function run(): void
    {
        $shift = CashierShift::open()->first();
        $user = User::where('username', 'meidina')->first();

        if (! $shift || ! $user) {
            $this->command->warn('Shift atau user meidina belum ada. Jalankan php artisan migrate:fresh --seed dulu.');
            return;
        }

        
        $demo = [
            ['MEJA-01', RentalSession::MODE_PACKAGE, 'Andi (kuning)', 'Paket 1 Jam', 52],
            ['MEJA-04', RentalSession::MODE_PACKAGE, 'Sari (merah)', 'Paket 1 Jam', 65],
            ['MEJA-06', RentalSession::MODE_OPEN, 'Doni (oranye)', null, 75],
        ];

        foreach ($demo as [$code, $mode, $customer, $packageName, $minutesAgo]) {
            $console = Console::where('code', $code)->firstOrFail();

            if ($console->activeSession) {
                $this->command->warn("$code sudah dipakai, dilewati.");
                continue;
            }

            $package = $packageName
                ? PricingPackage::where('category_id', $console->category_id)->where('name', $packageName)->first()
                : null;

            $start = now()->subMinutes($minutesAgo);

            RentalSession::create([
                'invoice_number' => 'DEMO-' . now()->format('Ymd') . '-' . $code,
                'console_id' => $console->id,
                'shift_id' => $shift->id,
                'user_id' => $user->id,
                'customer_name' => $customer,
                'billing_mode' => $mode,
                'package_id' => $package?->id,
                'start_time' => $start,
                'planned_end_time' => $package ? $start->copy()->addMinutes($package->duration_minutes) : null,
                'total_minutes' => $package?->duration_minutes ?? 0,
                'rental_amount' => $package?->price ?? 0,
            ]);

            $console->update(['status' => Console::OCCUPIED]);
        }
    }
}
