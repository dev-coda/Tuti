<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->string('zip_code')->nullable()->after('tax_group');
            $table->string('fulfillment_provider_48h')->default('coordinadora')->after('zip_code');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_provider')->nullable()->after('delivery_method');
            $table->decimal('shipping_quote_amount', 12, 2)->nullable()->after('shipping_provider');

            $table->string('coordinadora_guide_number')->nullable()->after('shipping_quote_amount');
            $table->string('coordinadora_status_code')->nullable()->after('coordinadora_guide_number');
            $table->string('coordinadora_status_text')->nullable()->after('coordinadora_status_code');
            $table->timestamp('coordinadora_status_at')->nullable()->after('coordinadora_status_text');

            $table->string('fv_number')->nullable()->after('coordinadora_status_at');
            $table->longText('fv_request_payload')->nullable()->after('fv_number');
            $table->longText('fv_response_payload')->nullable()->after('fv_request_payload');
            $table->longText('coordinadora_request_payload')->nullable()->after('fv_response_payload');
            $table->longText('coordinadora_response_payload')->nullable()->after('coordinadora_request_payload');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_provider',
                'shipping_quote_amount',
                'coordinadora_guide_number',
                'coordinadora_status_code',
                'coordinadora_status_text',
                'coordinadora_status_at',
                'fv_number',
                'fv_request_payload',
                'fv_response_payload',
                'coordinadora_request_payload',
                'coordinadora_response_payload',
            ]);
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn([
                'zip_code',
                'fulfillment_provider_48h',
            ]);
        });
    }
};
