<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('shipping_methods')
            ->where('code', 'tronex')
            ->update([
                'name' => 'Entrega Standard',
                'description' => 'Envío gratis.',
                'updated_at' => now(),
            ]);

        DB::table('shipping_methods')
            ->where('code', 'express')
            ->update([
                'name' => 'Entrega Especial',
                'description' => 'Realiza tu pedido de lunes a viernes antes de las 5:00 pm para recibir en 48 horas. Sábado y domingo realiza pedido las 24 horas y recíbelo en 48 horas del siguiente día hábil. Aplica para ciudades principales.',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('shipping_methods')
            ->where('code', 'tronex')
            ->update([
                'name' => 'Entrega programada (Tronex)',
                'description' => 'Entrega según el ciclo de ruta programado',
                'updated_at' => now(),
            ]);

        DB::table('shipping_methods')
            ->where('code', 'express')
            ->update([
                'name' => 'Entrega en 48h',
                'description' => 'Entrega express en 48 horas',
                'updated_at' => now(),
            ]);
    }
};
