<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'inventory_opt_out')) {
                $table->boolean('inventory_opt_out')->nullable()->after('safety_stock');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'inventory_opt_out')) {
                $table->boolean('inventory_opt_out')->nullable()->after('active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'inventory_opt_out')) {
                $table->dropColumn('inventory_opt_out');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'inventory_opt_out')) {
                $table->dropColumn('inventory_opt_out');
            }
        });
    }
};
