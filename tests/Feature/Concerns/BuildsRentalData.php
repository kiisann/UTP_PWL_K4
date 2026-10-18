<?php

namespace Tests\Feature\Concerns;

use App\Models\CashierShift;
use App\Models\Console;
use App\Models\ConsoleCategory;
use App\Models\PricingPackage;
use App\Models\Product;
use App\Models\User;

/** Pembuat data uji sederhana (tanpa factory) untuk fitur kasir. */
trait BuildsRentalData
{
    protected function makeCashier(string $username = 'kasir1'): User
    {
        return User::create([
            'name' => 'Kasir ' . $username,
            'username' => $username,
            'password' => 'password',
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    protected function openShift(User $user, float $startingCash = 100000): CashierShift
    {
        return CashierShift::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'starting_cash' => $startingCash,
            'expected_cash' => $startingCash,
            'qris_total' => 0,
        ]);
    }

    protected function makeCategory(float $rate = 10000): ConsoleCategory
    {
        return ConsoleCategory::create([
            'name' => 'PS4 Uji ' . uniqid(),
            'hourly_rate' => $rate,
            'min_charge_minutes' => 30,
            'rounding_minutes' => 15,
            'description' => 'uji',
        ]);
    }

    protected function makeConsole(ConsoleCategory $category, string $code, string $status = Console::AVAILABLE): Console
    {
        return Console::create([
            'category_id' => $category->id,
            'code' => $code,
            'name' => "Meja {$code}",
            'status' => $status,
        ]);
    }

    protected function makePackage(ConsoleCategory $category, int $minutes = 60, float $price = 9000): PricingPackage
    {
        return PricingPackage::create([
            'category_id' => $category->id,
            'name' => "Paket {$minutes} menit",
            'duration_minutes' => $minutes,
            'price' => $price,
            'is_active' => true,
        ]);
    }

    protected function makeProduct(int $stock = 10, float $price = 5000): Product
    {
        return Product::create([
            'sku' => 'SKU' . uniqid(),
            'name' => 'Mi Instan',
            'category' => 'makanan',
            'cost_price' => 3000,
            'sell_price' => $price,
            'stock' => $stock,
            'is_active' => true,
        ]);
    }
}
