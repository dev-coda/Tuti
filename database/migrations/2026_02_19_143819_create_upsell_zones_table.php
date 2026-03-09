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
        Schema::create('upsell_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('display_title')->nullable(); // Custom title to show on frontend
            $table->boolean('active')->default(true);
            $table->integer('position')->default(0);
            $table->integer('max_products')->default(4); // Maximum products to display
            $table->string('context')->default('product_detail'); // product_detail, cart, checkout, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upsell_zones');
    }
};
