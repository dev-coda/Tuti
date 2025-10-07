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
        Schema::create('volume_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // Discount configuration
            $table->enum('discount_type', ['percentage', 'fixed_amount']);
            $table->decimal('discount_value', 10, 2);

            // Volume thresholds
            $table->integer('min_quantity');
            $table->integer('max_quantity')->nullable();

            // Application rules
            $table->enum('applies_to', ['products', 'categories', 'brands', 'vendors', 'cart']);
            $table->json('applies_to_ids')->nullable();

            // Validity period
            $table->datetime('valid_from');
            $table->datetime('valid_to');

            // Status
            $table->boolean('active')->default(true);

            $table->timestamps();
        });

        Schema::create('volume_discount_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('volume_discount_id');
            $table->unsignedBigInteger('product_id');
            $table->foreign('volume_discount_id')->references('id')->on('volume_discounts')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volume_discount_products');
        Schema::dropIfExists('volume_discounts');
    }
};
