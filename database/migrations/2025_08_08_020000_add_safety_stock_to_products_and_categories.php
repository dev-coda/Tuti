<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'safety_stock')) {
                $table->unsignedInteger('safety_stock')->default(0)->after('calculate_package_price');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'safety_stock')) {
                $table->unsignedInteger('safety_stock')->default(0)->after('active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'safety_stock')) {
                $table->dropColumn('safety_stock');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'safety_stock')) {
                $table->dropColumn('safety_stock');
            }
        });
    }
};
