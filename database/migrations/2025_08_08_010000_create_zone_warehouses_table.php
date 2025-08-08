<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zone_warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('zone_code')->index();
            $table->string('bodega_code')->index();
            $table->timestamps();
            $table->unique(['zone_code', 'bodega_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_warehouses');
    }
};
