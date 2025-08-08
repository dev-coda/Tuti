<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create setting if not exists
        $exists = DB::table('settings')->where('key', 'auto_updater_enabled')->exists();
        if (!$exists) {
            DB::table('settings')->insert([
                'name' => 'Habilitar auto-actualización de precios',
                'key' => 'auto_updater_enabled',
                'value' => '0',
                'show' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'auto_updater_enabled')->delete();
    }
};
