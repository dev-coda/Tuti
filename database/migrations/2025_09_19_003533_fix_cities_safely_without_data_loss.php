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
            ['name' => 'Bogotá', 'state_name' => 'BOGOTA D.C.', 'active' => true, 'is_preferred' => true],
            ['name' => 'Villavicencio', 'state_name' => 'META', 'active' => true, 'is_preferred' => true],
            ['name' => 'Medellín', 'state_name' => 'ANTIOQUIA', 'active' => true, 'is_preferred' => true],
            ['name' => 'Montería', 'state_name' => 'CORDOBA', 'active' => true, 'is_preferred' => true],
            ['name' => 'Cali', 'state_name' => 'VALLE DEL CAUCA', 'active' => true, 'is_preferred' => true],
            ['name' => 'Pereira', 'state_name' => 'RISARALDA', 'active' => true, 'is_preferred' => true],
            ['name' => 'Cúcuta', 'state_name' => 'NORTE DE SANTANDER', 'active' => true, 'is_preferred' => true],
            ['name' => 'Bucaramanga', 'state_name' => 'SANTANDER', 'active' => true, 'is_preferred' => true],
            ['name' => 'Barranquilla', 'state_name' => 'ATLANTICO', 'active' => true, 'is_preferred' => true],
            ['name' => 'Cartagena', 'state_name' => 'BOLIVAR', 'active' => true, 'is_preferred' => true],
            ['name' => 'Valledupar', 'state_name' => 'CESAR', 'active' => true, 'is_preferred' => true],
        ];

        foreach ($preferredCities as $cityData) {
            $stateId = State::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($cityData['state_name'])])
                ->value('id');

            if (!$stateId) {
                continue;
            }

            $payload = [
                'name' => $cityData['name'],
                'state_id' => $stateId,
                'active' => $cityData['active'],
                'is_preferred' => $cityData['is_preferred'],
            ];

            // Only create if it doesn't already exist (avoid duplicates)
            City::firstOrCreate(
                ['name' => $payload['name'], 'state_id' => $payload['state_id']],
                $payload
            );
        }

        // Step 4: Update any existing matching cities to be preferred
        foreach ($preferredCities as $cityData) {
            $stateId = State::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($cityData['state_name'])])
                ->value('id');

            if (!$stateId) {
                continue;
            }

            City::where('name', $cityData['name'])
                ->where('state_id', $stateId)
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
