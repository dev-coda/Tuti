<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            // When false, the coupon is exempt from products that already carry
            // a brand or vendor discount (descuento directo de marca/proveedor).
            $table->boolean('apply_on_brand_vendor_discounts')->default(true)->after('minimum_amount');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('apply_on_brand_vendor_discounts');
        });
    }
};
