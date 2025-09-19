<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\City;
use App\Models\State;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ⚠️  WARNING: THIS MIGRATION IS DESTRUCTIVE AND HAS BEEN DISABLED ⚠️
     * 
     * This migration was causing data loss by truncating cities table
     * which broke foreign key relationships with users, orders, and contacts.
     * 
     * Use the safe migration instead: 2025_09_19_003533_fix_cities_safely_without_data_loss
     */
    public function up(): void
    {
        // ⚠️  DISABLED: This truncation caused massive data loss
        // City::truncate();

        // This migration has been replaced with a safer approach
        // See: database/migrations/2025_09_19_003533_fix_cities_safely_without_data_loss.php
        return;

        // Map cities to their state IDs (based on existing states table)
        // From the states list: Bogotá D.C.=31, Meta=7, Antioquia=1, etc.
        $citiesData = [
            ['name' => 'Bogotá', 'state_id' => 31], // Bogotá D.C.
            ['name' => 'Villavicencio', 'state_id' => 7], // Meta
            ['name' => 'Medellín', 'state_id' => 1], // Antioquia
            ['name' => 'Montería', 'state_id' => 3], // Córdoba
            ['name' => 'Cali', 'state_id' => 33], // Valle del Cauca
            ['name' => 'Pereira', 'state_id' => 19], // Risaralda
            ['name' => 'Cúcuta', 'state_id' => 32], // Norte de Santander
            ['name' => 'Bucaramanga', 'state_id' => 6], // Santander
            ['name' => 'Barranquilla', 'state_id' => 8], // Atlántico
            ['name' => 'Cartagena', 'state_id' => 9], // Bolívar
            ['name' => 'Valledupar', 'state_id' => 13], // Cesar
        ];

        // Insert new cities
        foreach ($citiesData as $cityData) {
            City::create([
                'name' => $cityData['name'],
                'state_id' => $cityData['state_id'],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be easily reversed as we don't know the original cities
        // You would need to restore from a backup if needed
    }
};
