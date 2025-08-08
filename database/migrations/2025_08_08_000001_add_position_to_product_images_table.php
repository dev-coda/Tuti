<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('path');
        });

        // Backfill: set position = id to preserve current visual order
        DB::table('product_images')->update(['position' => DB::raw('id')]);
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
