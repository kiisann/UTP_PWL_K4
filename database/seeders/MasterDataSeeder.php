<?php

namespace Database\Seeders;

use App\Models\Console;
use App\Models\ConsoleCategory;
use App\Models\PricingPackage;
use App\Models\Product;
use Illuminate\Database\Seeder;


class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
       
        $categoryData = [
            'PS3'      => [6000,  'Konsol PS3 reguler'],
            'PS4 Slim' => [10000, 'Konsol PS4 Slim reguler'],
            'PS4 Pro'  => [12000, 'Konsol PS4 Pro, grafis lebih tinggi'],
            'PS5'      => [15000, 'Konsol PS5'],
            'VIP Room' => [25000, 'Ruang VIP privat dengan PS5'],
        ];

        $categories = [];
        foreach ($categoryData as $name => [$rate, $description]) {
            $categories[$name] = ConsoleCategory::updateOrCreate(
                ['name' => $name],
                [
                    'hourly_rate' => $rate,
                    'min_charge_minutes' => 30,
                    'rounding_minutes' => 15,
                    'description' => $description,
                ]
            );
        }

        
        $consoles = [
            ['MEJA-01', 'MEJA 01 - PS3',      'PS3',      Console::AVAILABLE],
            ['MEJA-02', 'MEJA 02 - PS4 Slim', 'PS4 Slim', Console::AVAILABLE],
            ['MEJA-03', 'MEJA 03 - PS4 Slim', 'PS4 Slim', Console::AVAILABLE],
            ['MEJA-04', 'MEJA 04 - PS4 Pro',  'PS4 Pro',  Console::AVAILABLE],
            ['MEJA-05', 'MEJA 05 - PS5',      'PS5',      Console::AVAILABLE],
            ['MEJA-06', 'MEJA 06 - PS5',      'PS5',      Console::AVAILABLE],
            ['MEJA-07', 'MEJA 07 - VIP Room', 'VIP Room', Console::AVAILABLE],
           
            ['MEJA-08', 'MEJA 08 - PS4 Slim', 'PS4 Slim', Console::MAINTENANCE],
        ];

        foreach ($consoles as [$code, $name, $categoryName, $status]) {
            Console::updateOrCreate(
                ['code' => $code],
                [
                    'category_id' => $categories[$categoryName]->id,
                    'name' => $name,
                    'status' => $status,
                ]
            );
        }

       
        $packages = [
            'PS3' => [
                ['Paket 1 Jam', 60, 5000],
                ['Paket 3 Jam', 180, 14000],
            ],
            'PS4 Slim' => [
                ['Paket 1 Jam', 60, 9000],
                ['Paket 3 Jam', 180, 25000],
                ['Paket 5 Jam', 300, 40000],
            ],
            'PS4 Pro' => [
                ['Paket 1 Jam', 60, 11000],
                ['Paket 3 Jam', 180, 30000],
            ],
            'PS5' => [
                ['Paket 1 Jam', 60, 14000],
                ['Paket 3 Jam', 180, 38000],
            ],
            'VIP Room' => [
                ['Paket 1 Jam', 60, 23000],
                ['Paket 3 Jam', 180, 65000],
            ],
        ];

        foreach ($packages as $categoryName => $list) {
            foreach ($list as [$name, $minutes, $price]) {
                PricingPackage::updateOrCreate(
                    [
                        'category_id' => $categories[$categoryName]->id,
                        'name' => $name,
                    ],
                    [
                        'duration_minutes' => $minutes,
                        'price' => $price,
                        'is_active' => true,
                    ]
                );
            }
        }

        
        $products = [
            ['IDM-GRG', 'Indomie Goreng',   'makanan', 3000, 6000, 50],
            ['IDM-KUA', 'Indomie Kuah',     'makanan', 3000, 6000, 50],
            ['SNK-KRP', 'Keripik Kentang',  'camilan', 4000, 8000, 30],
            ['SNK-ROT', 'Roti Cokelat',     'camilan', 3500, 6000, 30],
            ['DRK-TEH', 'Teh Botol',        'minuman', 3500, 6000, 40],
            ['DRK-ESN', 'Es Teh Manis',     'minuman', 1500, 4000, 40],
            ['DRK-AIR', 'Air Mineral',      'minuman', 2000, 4000, 60],
            ['DRK-KOP', 'Kopi Sachet',      'minuman', 2000, 5000, 40],
        ];

        foreach ($products as [$sku, $name, $category, $cost, $sell, $stock]) {
            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'category' => $category,
                    'cost_price' => $cost,
                    'sell_price' => $sell,
                    'stock' => $stock,
                    'is_active' => true,
                ]
            );
        }
    }
}
