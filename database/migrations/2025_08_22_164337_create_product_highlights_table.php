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
        Schema::create('product_highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('position')->default(1); // 1-4 for first row positions
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Ensure unique category-product combination
            $table->unique(['category_id', 'product_id']);

            // Ensure position uniqueness within category (max 4 positions)
            $table->unique(['category_id', 'position']);

            // Index for performance
            $table->index(['category_id', 'active', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_highlights');
    }
};
