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
        Schema::table('order_product_bonifications', function (Blueprint $table) {
            $table->foreignId('variation_item_id')->nullable()->after('product_id')->constrained('variation_items')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_product_bonifications', function (Blueprint $table) {
            $table->dropForeign(['variation_item_id']);
            $table->dropColumn('variation_item_id');
        });
    }
};
