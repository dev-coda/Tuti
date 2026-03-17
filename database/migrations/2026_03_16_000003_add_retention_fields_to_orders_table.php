<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tax_group')->nullable()->after('coupon_discount');
            $table->decimal('retention_fuente', 15, 2)->default(0)->after('tax_group');
            $table->decimal('retention_iva', 15, 2)->default(0)->after('retention_fuente');
            $table->decimal('retention_total', 15, 2)->default(0)->after('retention_iva');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tax_group', 'retention_fuente', 'retention_iva', 'retention_total']);
        });
    }
};
