<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * apply_on_brand_vendor_discounts now means "the coupon STACKS on top of
     * brand/vendor discounts". By default coupons must NOT stack (the best
     * discount wins per line). Stacking never existed before, so no coupon
     * was intentionally configured to stack: the column is recreated with a
     * false default, resetting every existing coupon to the new default.
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('apply_on_brand_vendor_discounts');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('apply_on_brand_vendor_discounts')->default(false)->after('minimum_amount');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('apply_on_brand_vendor_discounts');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('apply_on_brand_vendor_discounts')->default(true)->after('minimum_amount');
        });
    }
};
