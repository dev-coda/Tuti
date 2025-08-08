<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('bodega_code')->index();
            $table->integer('available')->default(0);
            $table->integer('physical')->default(0);
            $table->integer('reserved')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'bodega_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_inventories');
    }
};
