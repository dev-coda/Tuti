<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_inventories', function (Blueprint $table) {
            $table->dropUnique('product_inventories_product_id_bodega_code_unique');
            $table->foreignId('variation_item_id')->nullable()->after('product_id')->constrained('variation_items')->nullOnDelete();
            $table->string('source_sku')->nullable()->after('variation_item_id')->index();
            $table->unique(['product_id', 'variation_item_id', 'bodega_code'], 'product_inventories_product_variation_bodega_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product_inventories', function (Blueprint $table) {
            $table->dropUnique('product_inventories_product_variation_bodega_unique');
            $table->dropColumn('source_sku');
            $table->dropConstrainedForeignId('variation_item_id');
            $table->unique(['product_id', 'bodega_code']);
        });
    }
};
