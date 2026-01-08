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
        Schema::table('users', function (Blueprint $table) {
            // Contact information (phone already exists)
            $table->string('mobile_phone')->nullable()->after('phone');
            $table->string('whatsapp')->nullable()->after('mobile_phone');

            // Business information
            $table->string('business_name')->nullable()->after('name');
            $table->string('account_num')->nullable()->after('business_name');

            // Location data
            $table->string('city_code')->nullable()->after('city_id');
            $table->string('county_id')->nullable()->after('city_code');

            // Business classification
            $table->string('customer_type')->nullable()->after('county_id');
            $table->string('price_group')->nullable()->after('customer_type');
            $table->string('tax_group')->nullable()->after('price_group');
            $table->string('line_discount')->nullable()->after('tax_group');

            // Operational data
            $table->decimal('balance', 15, 2)->default(0)->after('line_discount');
            $table->decimal('quota_value', 15, 2)->default(0)->after('balance');
            $table->string('customer_status')->nullable()->after('quota_value');
            $table->boolean('is_locked')->default(false)->after('customer_status');
            $table->integer('order_sequence')->nullable()->after('is_locked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop columns in reverse order (excluding phone which already existed)
            $table->dropColumn([
                'mobile_phone',
                'whatsapp',
                'business_name',
                'account_num',
                'city_code',
                'county_id',
                'customer_type',
                'price_group',
                'tax_group',
                'line_discount',
                'balance',
                'quota_value',
                'customer_status',
                'is_locked',
                'order_sequence'
            ]);
        });
    }
};
