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
        Schema::table('order_products', function (Blueprint $table) {
            // Track what type of discount was applied (percentage goes in discount field, fixed_amount is applied to price)
            $table->enum('discount_type', ['percentage', 'fixed_amount'])->default('percentage')->after('percentage');
            // Store the flat discount amount if discount_type is fixed_amount
            $table->decimal('flat_discount_amount', 10, 2)->default(0)->after('discount_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn('discount_type');
            $table->dropColumn('flat_discount_amount');
        });
    }
};
