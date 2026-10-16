<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::create([
            'name' => 'Test User',
            'username' => 'testuser',
            'password' => 'password',
        ]);

        User::create([
            'name' => 'Meidina',
            'username' => 'meidina',
            'password' => 'password',
            'role' => 'cashier',
        ]);
                $this->call(MasterDataSeeder::class);
                
    }
}