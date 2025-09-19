<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create setting if not exists
        $exists = DB::table('settings')->where('key', 'free_shipping_message')->exists();
        if (!$exists) {
            DB::table('settings')->insert([
                'name' => 'Mensaje de envíos gratis (barra superior)',
                'key' => 'free_shipping_message',
                'value' => 'Envíos gratis por compras mayores a $22.000',
                'show' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'free_shipping_message')->delete();
    }
};