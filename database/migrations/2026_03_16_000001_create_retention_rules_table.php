<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_rules', function (Blueprint $table) {
            $table->id();
            $table->string('tax_group');              // C_NORETIE, C_NAL, C_NAL_GRC
            $table->string('product_type');            // articulo, flete
            $table->decimal('base_rte_fuente', 15, 2)->default(0);
            $table->decimal('pct_rte_fuente', 8, 4)->default(0);   // stored as %, e.g. 2.5
            $table->decimal('base_rte_iva', 15, 2)->default(0);
            $table->decimal('pct_rte_iva', 8, 4)->default(0);      // stored as %, e.g. 15
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tax_group', 'product_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_rules');
    }
};
