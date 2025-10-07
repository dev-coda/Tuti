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
        Schema::create('discount_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id');
            $table->string('discount_type'); // 'product', 'brand', 'vendor', 'coupon', 'promocion', 'volume_discount', 'bonification'
            $table->unsignedBigInteger('discount_id')->nullable(); // ID of the specific discount (brand_id, coupon_id, etc.)
            $table->string('discount_name'); // Name of the discount for display
            $table->enum('discount_value_type', ['percentage', 'fixed_amount']);
            $table->decimal('discount_value', 10, 2); // The actual discount value
            $table->decimal('discount_amount', 10, 2); // The calculated discount amount applied
            $table->decimal('original_amount', 10, 2); // Original amount before discount
            $table->decimal('final_amount', 10, 2); // Final amount after discount
            $table->json('applied_to')->nullable(); // JSON array of product IDs or cart items affected
            $table->text('notes')->nullable(); // Additional notes about the application
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['discount_type', 'discount_id']);
            $table->index(['order_id', 'discount_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_applications');
    }
};
