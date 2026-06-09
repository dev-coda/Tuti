<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->unsignedBigInteger('variation_item_id')
                ->nullable()
                ->after('product_id');

            $table->foreign('variation_item_id')
                ->references('id')
                ->on('variation_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropForeign(['variation_item_id']);
            $table->dropColumn('variation_item_id');
        });
    }
};
