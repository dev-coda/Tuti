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
        Schema::create('bonification_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bonification_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id'); // Product that triggered the bonification
            $table->unsignedBigInteger('bonus_product_id'); // Product given as bonus
            $table->integer('bonus_quantity');
            $table->decimal('bonus_value', 10, 2); // Estimated value of the bonus
            $table->decimal('order_total', 10, 2);
            $table->integer('trigger_quantity'); // Quantity that triggered the bonification
            $table->string('user_email');
            $table->string('user_name')->nullable();
            $table->timestamps();

            $table->foreign('bonification_id')->references('id')->on('bonifications')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('bonus_product_id')->references('id')->on('products')->onDelete('cascade');

            $table->index(['bonification_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonification_analytics');
    }
};
