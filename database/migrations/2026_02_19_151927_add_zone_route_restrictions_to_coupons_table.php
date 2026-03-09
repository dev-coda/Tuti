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
            // Zone and route restrictions
            $table->json('allowed_zone_ids')->nullable()->after('except_customer_types'); // Zone IDs (from zones table)
            $table->json('allowed_zones')->nullable()->after('allowed_zone_ids'); // Zone numbers (string values from users.zone)
            $table->json('allowed_routes')->nullable()->after('allowed_zones'); // Route values (string values from zones.route)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['allowed_zone_ids', 'allowed_zones', 'allowed_routes']);
        });
    }
};
