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
            $table->text('technical_specifications')->nullable()->after('description');
            $table->text('warranty')->nullable()->after('technical_specifications');
            $table->text('other_information')->nullable()->after('warranty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['technical_specifications', 'warranty', 'other_information']);
        });
    }
};
