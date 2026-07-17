<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_dimension_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('success');
            $table->string('item_id_filter')->nullable();
            $table->unsignedInteger('items_received')->default(0);
            $table->unsignedInteger('items_with_dimensions')->default(0);
            $table->unsignedInteger('products_updated')->default(0);
            $table->json('unmatched_skus')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_dimension_sync_logs');
    }
};
