<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('settings')->where('key', 'inventory_sync_enabled')->exists();
        if (!$exists) {
            DB::table('settings')->insert([
                'name' => 'Habilitar sincronización nocturna de inventario',
                'key' => 'inventory_sync_enabled',
                'value' => '1',
                'show' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'inventory_sync_enabled')->delete();
    }
};
