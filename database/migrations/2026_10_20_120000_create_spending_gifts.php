<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spending_gifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('minimum_amount', 12, 2);
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedInteger('gift_quantity')->default(1);
            $table->decimal('gift_invoice_unit_value', 12, 2)->nullable();
            $table->string('gift_proration_scope', 16)->default('cart');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('spending_gift_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spending_gift_id')->constrained('spending_gifts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unique(['spending_gift_id', 'product_id']);
        });

        Schema::table('order_product_bonifications', function (Blueprint $table) {
            $table->foreignId('spending_gift_id')->nullable()->after('bonification_id')->constrained('spending_gifts')->nullOnDelete();
        });

        DB::statement('ALTER TABLE order_product_bonifications ALTER COLUMN bonification_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DELETE FROM order_product_bonifications WHERE bonification_id IS NULL');
        DB::statement('ALTER TABLE order_product_bonifications ALTER COLUMN bonification_id SET NOT NULL');

        Schema::table('order_product_bonifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('spending_gift_id');
        });

        Schema::dropIfExists('spending_gift_product');
        Schema::dropIfExists('spending_gifts');
    }
};
