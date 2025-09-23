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
        Schema::create('promocions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // Discount configuration
            $table->enum('discount_type', ['percentage', 'fixed_amount']);
            $table->decimal('discount_value', 10, 2);

            // Validity period
            $table->datetime('valid_from');
            $table->datetime('valid_to');

            // Application level and rules
            $table->enum('level', ['products', 'categories', 'brands', 'vendors', 'zones']);
            $table->json('level_ids')->nullable(); // IDs of the level items

            // Minimum requirements
            $table->decimal('minimum_cart_value', 10, 2)->nullable();
            $table->integer('minimum_cart_units')->nullable();

            // Usage limits
            $table->integer('usage_limit')->nullable();
            $table->integer('current_usage')->default(0);

            // Status
            $table->boolean('active')->default(true);

            $table->timestamps();
        });

        Schema::create('promocion_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promocion_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('discount_amount', 10, 2);
            $table->timestamps();

            $table->foreign('promocion_id')->references('id')->on('promocions')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promocion_usages');
        Schema::dropIfExists('promocions');
    }
};
