<?php

namespace Database\Seeders;

use App\Models\CashierShift;
use App\Models\Console;
use App\Models\PricingPackage;
use App\Models\RentalSession;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * KHUSUS UJI TAMPILAN. Membuat 2 sesi bermain palsu supaya kartu biru
 * dan oranye di dashboard bisa dilihat sebelum fitur "Mulai Main" jadi.
 *
 * Jalankan manual: php artisan db:seed --class=DemoSessionSeeder
 * Jangan dipanggil dari DatabaseSeeder. Reset dengan: php artisan migrate:fresh --seed
 */
class DemoSessionSeeder extends Seeder
{
    public function run(): void
    {
        $shift = CashierShift::open()->first();
        $user = User::where('username', 'meidina')->first();

        if (! $shift || ! $user) {
            $this->command->warn('Shift atau user meidina belum ada. Jalankan php artisan migrate:fresh --seed dulu.');
            return;
        }

        // [kode meja, mode, nama pelanggan, nama paket]
        $demo = [
            ['MEJA-02', RentalSession::MODE_PACKAGE, 'Budi', 'Paket 3 Jam'],
            ['MEJA-05', RentalSession::MODE_OPEN, 'Rina', null],
        ];

        foreach ($demo as [$code, $mode, $customer, $packageName]) {
            $console = Console::where('code', $code)->firstOrFail();

            if ($console->activeSession) {
                continue;
            }

            $package = $packageName
                ? PricingPackage::where('category_id', $console->category_id)->where('name', $packageName)->first()
                : null;

            $start = now()->subMinutes(30);

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
                'rental_amount' => $package?->price ?? 0,
            ]);

            $console->update(['status' => Console::OCCUPIED]);
        }
    }
}
