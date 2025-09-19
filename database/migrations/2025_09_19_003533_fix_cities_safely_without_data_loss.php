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
     */
    public function up(): void
    {
        // Step 1: Add 'active' column to cities table to control visibility
        Schema::table('cities', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('state_id');
            $table->boolean('is_preferred')->default(false)->after('active'); // Mark preferred cities for registration
        });

        // Step 2: Mark existing cities as non-preferred (but keep them active for existing data)
        City::query()->update(['is_preferred' => false]);

        // Step 3: Add new preferred cities (without destroying existing ones)
        $preferredCities = [
            ['name' => 'Bogotá', 'state_id' => 31, 'active' => true, 'is_preferred' => true],
            ['name' => 'Villavicencio', 'state_id' => 7, 'active' => true, 'is_preferred' => true],
            ['name' => 'Medellín', 'state_id' => 1, 'active' => true, 'is_preferred' => true],
            ['name' => 'Montería', 'state_id' => 3, 'active' => true, 'is_preferred' => true],
            ['name' => 'Cali', 'state_id' => 33, 'active' => true, 'is_preferred' => true],
            ['name' => 'Pereira', 'state_id' => 19, 'active' => true, 'is_preferred' => true],
            ['name' => 'Cúcuta', 'state_id' => 32, 'active' => true, 'is_preferred' => true],
            ['name' => 'Bucaramanga', 'state_id' => 6, 'active' => true, 'is_preferred' => true],
            ['name' => 'Barranquilla', 'state_id' => 8, 'active' => true, 'is_preferred' => true],
            ['name' => 'Cartagena', 'state_id' => 9, 'active' => true, 'is_preferred' => true],
            ['name' => 'Valledupar', 'state_id' => 13, 'active' => true, 'is_preferred' => true],
        ];

        foreach ($preferredCities as $cityData) {
            // Only create if it doesn't already exist (avoid duplicates)
            City::firstOrCreate(
                ['name' => $cityData['name'], 'state_id' => $cityData['state_id']],
                $cityData
            );
        }

        // Step 4: Update any existing matching cities to be preferred
        foreach ($preferredCities as $cityData) {
            City::where('name', $cityData['name'])
                ->where('state_id', $cityData['state_id'])
                ->update(['is_preferred' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the columns we added
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['active', 'is_preferred']);
        });
    }
};
