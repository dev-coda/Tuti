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
        Schema::create('upsell_zone_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upsell_zone_id')->constrained('upsell_zones')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['upsell_zone_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upsell_zone_products');
    }
};
