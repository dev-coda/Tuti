<?php

namespace Database\Seeders;

use App\Models\PackageType;
use Illuminate\Database\Seeder;

class PackageTypeSeeder extends Seeder
{
    /**
     * Default tamaños de empaque. Adjust from the admin panel
     * (Configuraciones > Tamaños de Empaque) to match the sizes
     * agreed with Coordinadora.
     */
    public function run(): void
    {
        $defaults = [
            ['code' => 'XS', 'name' => 'Extra pequeño', 'max_weight_kg' => 1, 'max_length_cm' => 25, 'max_width_cm' => 20, 'max_height_cm' => 10, 'position' => 1],
            ['code' => 'S', 'name' => 'Pequeño', 'max_weight_kg' => 3, 'max_length_cm' => 30, 'max_width_cm' => 25, 'max_height_cm' => 15, 'position' => 2],
            ['code' => 'M', 'name' => 'Mediano', 'max_weight_kg' => 8, 'max_length_cm' => 40, 'max_width_cm' => 30, 'max_height_cm' => 25, 'position' => 3],
            ['code' => 'L', 'name' => 'Grande', 'max_weight_kg' => 15, 'max_length_cm' => 60, 'max_width_cm' => 40, 'max_height_cm' => 40, 'position' => 4],
            ['code' => 'XL', 'name' => 'Extra grande', 'max_weight_kg' => 25, 'max_length_cm' => 100, 'max_width_cm' => 60, 'max_height_cm' => 60, 'position' => 5],
        ];

        foreach ($defaults as $default) {
            PackageType::firstOrCreate(['code' => $default['code']], $default + ['active' => true]);
        }
    }
}
