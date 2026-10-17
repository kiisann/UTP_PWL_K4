<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator (Owner)',
                'password' => 'password',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['username' => 'testuser'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'role' => 'cashier',
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['username' => 'meidina'],
            [
                'name' => 'Meidina',
                'password' => 'password',
                'role' => 'cashier',
                'is_active' => true,
            ]
        );

        $this->call(MasterDataSeeder::class);
    }
}
