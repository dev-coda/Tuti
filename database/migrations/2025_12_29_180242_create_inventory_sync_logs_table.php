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
        Schema::create('inventory_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('bodega_code');
            $table->integer('skus_received')->default(0);
            $table->integer('products_updated')->default(0);
            $table->integer('products_set_to_zero')->default(0);
            $table->json('skus_in_response')->nullable();
            $table->longText('soap_response')->nullable(); // Full SOAP XML response
            $table->string('status')->default('success'); // success, error, warning
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index('bodega_code');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_sync_logs');
    }
};
