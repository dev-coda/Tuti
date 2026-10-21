<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bonifications', 'sum_products')) {
            Schema::table('bonifications', function (Blueprint $table) {
                $table->dropColumn('sum_products');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bonifications', 'sum_products')) {
            Schema::table('bonifications', function (Blueprint $table) {
                $table->boolean('sum_products')->default(false);
            });
        }
    }
};
