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
        Schema::create('upsell_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // recent_orders, favorite_products, same_category, same_brand, manual, best_selling, etc.
            $table->text('description')->nullable();
            $table->json('config')->nullable(); // Rule-specific configuration (e.g., days for recent orders, limit, etc.)
            $table->integer('priority')->default(0); // Higher priority rules are evaluated first
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upsell_rules');
    }
};
