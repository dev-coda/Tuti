<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->boolean('shipping_standard_enabled')->default(true)->after('fulfillment_provider_48h');
            $table->boolean('shipping_express_enabled')->default(true)->after('shipping_standard_enabled');
        });

        // Preserve prior behavior: a configured min (>0) meant free shipping was on.
        $min = (float) Setting::getByKeyWithDefault('express_free_shipping_min', '0');
        $enabled = $min > 0 ? '1' : '0';
        Setting::updateOrCreate(
            ['key' => 'express_free_shipping_enabled'],
            [
                'name' => 'Envío especial gratuito por compra mínima',
                'value' => $enabled,
                'show' => false,
            ]
        );
        Cache::forget('setting_express_free_shipping_enabled');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn(['shipping_standard_enabled', 'shipping_express_enabled']);
        });

        Setting::where('key', 'express_free_shipping_enabled')->delete();
        Cache::forget('setting_express_free_shipping_enabled');
    }
};
