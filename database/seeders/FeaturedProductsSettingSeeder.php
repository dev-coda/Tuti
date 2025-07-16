<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeaturedProductsSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::updateOrCreate(
            ['key' => 'use_most_sold_products'],
            [
                'name' => 'Usar productos más vendidos',
                'value' => '0',
                'show' => false
            ]
        );
    }
}
