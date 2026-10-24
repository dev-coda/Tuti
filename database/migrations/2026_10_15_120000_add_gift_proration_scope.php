<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonifications', function (Blueprint $table) {
            // cart = todo el carrito sin descuento. trigger = solo los productos que activan la bonificacion.
            $table->string('gift_proration_scope', 16)->default('cart');
        });

        Schema::table('order_product_bonifications', function (Blueprint $table) {
            $table->string('proration_scope', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_product_bonifications', function (Blueprint $table) {
            $table->dropColumn('proration_scope');
        });
        Schema::table('bonifications', function (Blueprint $table) {
            $table->dropColumn('gift_proration_scope');
        });
    }
};
