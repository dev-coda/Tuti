<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // 'tronex', 'express'
            $table->string('name'); // Display name
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Insert default shipping methods
        DB::table('shipping_methods')->insert([
            [
                'code' => 'tronex',
                'name' => 'Entrega programada (Tronex)',
                'description' => 'Entrega según el ciclo de ruta programado',
                'enabled' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'express',
                'name' => 'Entrega en 48h',
                'description' => 'Entrega express en 48 horas',
                'enabled' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
