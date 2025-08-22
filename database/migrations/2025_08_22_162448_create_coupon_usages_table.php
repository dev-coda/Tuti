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
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Customer who used the coupon
            $table->foreignId('order_id')->constrained()->onDelete('cascade'); // Order where coupon was used
            $table->foreignId('vendor_id')->nullable()->constrained()->onDelete('set null'); // Vendor if applicable
            $table->decimal('discount_amount', 10, 2); // Actual discount amount applied
            $table->timestamps();

            // Indexes for performance and uniqueness
            $table->index(['coupon_id', 'user_id']);
            $table->index(['coupon_id', 'vendor_id']);
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
