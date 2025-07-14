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
            $table->boolean('first_purchase_only')->default(false)->after('discount');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->boolean('first_purchase_only')->default(false)->after('discount');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->boolean('first_purchase_only')->default(false)->after('discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('first_purchase_only');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('first_purchase_only');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('first_purchase_only');
        });
    }
};
