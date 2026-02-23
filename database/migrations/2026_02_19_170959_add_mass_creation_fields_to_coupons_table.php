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
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('parent_coupon_id')->nullable()->after('id')->constrained('coupons')->onDelete('set null');
            $table->boolean('is_mass_created')->default(false)->after('parent_coupon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropForeign(['parent_coupon_id']);
            $table->dropColumn(['parent_coupon_id', 'is_mass_created']);
        });
    }
};
