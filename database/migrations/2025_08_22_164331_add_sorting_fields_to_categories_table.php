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
        Schema::table('categories', function (Blueprint $table) {
            $table->enum('default_sort_order', [
                'most_recent',
                'price_asc',
                'price_desc',
                'name_asc',
                'name_desc',
                'best_selling'
            ])->default('most_recent')->after('active');

            $table->json('highlighted_brand_ids')->nullable()->after('default_sort_order');
            $table->boolean('enable_highlighting')->default(false)->after('highlighted_brand_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['default_sort_order', 'highlighted_brand_ids', 'enable_highlighting']);
        });
    }
};
