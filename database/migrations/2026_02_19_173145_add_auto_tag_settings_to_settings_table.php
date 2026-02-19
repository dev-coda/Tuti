<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create auto tag settings if they don't exist
        Setting::firstOrCreate(
            ['key' => 'auto_tag_nuevo_enabled'],
            [
                'name' => 'Etiqueta automática NUEVO',
                'value' => '0',
                'show' => true,
            ]
        );

        Setting::firstOrCreate(
            ['key' => 'auto_tag_descuento_enabled'],
            [
                'name' => 'Etiqueta automática DESCUENTO',
                'value' => '0',
                'show' => true,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::whereIn('key', ['auto_tag_nuevo_enabled', 'auto_tag_descuento_enabled'])->delete();
    }
};
