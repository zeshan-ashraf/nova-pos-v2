<?php

namespace Database\Seeders;

use App\Models\Shop;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Shop::firstOrCreate(
            ['name' => 'Main Shop'],
            [
                'address' => 'Main Street',
                'phone' => '03000000000',
                'owner_name' => 'Admin',
                'is_parent' => true,
                'parent_shop_id' => null,
                'status' => true,
            ]
        );
    }
}
