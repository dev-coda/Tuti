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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Coupon code that customers will use
            $table->string('name'); // Display name for the coupon
            $table->text('description')->nullable(); // Optional description

            // Coupon type and value
            $table->enum('type', ['fixed_amount', 'percentage']); // Type of discount
            $table->decimal('value', 10, 2); // Discount value (amount or percentage)

            // Validity period
            $table->datetime('valid_from'); // Start date and time
            $table->datetime('valid_to'); // End date and time

            // Usage limits
            $table->integer('usage_limit_per_customer')->nullable(); // Max uses per customer
            $table->integer('usage_limit_per_vendor')->nullable(); // Max uses per vendor
            $table->integer('total_usage_limit')->nullable(); // Total max uses across all customers
            $table->integer('current_usage')->default(0); // Track current total usage

            // Application rules - what the coupon applies to
            $table->enum('applies_to', ['cart', 'product', 'category', 'brand', 'vendor', 'customer', 'customer_type']);
            $table->json('applies_to_ids')->nullable(); // IDs of the items this coupon applies to

            // Exception rules - what the coupon should NOT apply to
            $table->json('except_product_ids')->nullable(); // Product IDs to exclude
            $table->json('except_category_ids')->nullable(); // Category IDs to exclude
            $table->json('except_brand_ids')->nullable(); // Brand IDs to exclude
            $table->json('except_vendor_ids')->nullable(); // Vendor IDs to exclude
            $table->json('except_customer_ids')->nullable(); // Customer IDs to exclude
            $table->json('except_customer_types')->nullable(); // Customer types to exclude (role names)

            // Minimum purchase requirements
            $table->decimal('minimum_amount', 10, 2)->nullable(); // Minimum cart amount to apply coupon

            // Status
            $table->boolean('active')->default(true); // Whether the coupon is active

            $table->timestamps();

            // Indexes for performance
            $table->index('code');
            $table->index('active');
            $table->index(['valid_from', 'valid_to']);
            $table->index('applies_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
