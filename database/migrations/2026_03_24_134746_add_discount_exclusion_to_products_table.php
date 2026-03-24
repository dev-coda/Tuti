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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('exclude_from_brand_discount')->default(false)->after('inventory_opt_out');
            $table->boolean('exclude_from_vendor_discount')->default(false)->after('exclude_from_brand_discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['exclude_from_brand_discount', 'exclude_from_vendor_discount']);
        });
    }
};
