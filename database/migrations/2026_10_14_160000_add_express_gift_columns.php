<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonifications', function (Blueprint $table) {
            // Valor neto minimo (sin IVA) por unidad con el que se factura el obsequio en Express.
            $table->decimal('gift_invoice_unit_value', 14, 2)->nullable();
        });

        Schema::table('order_product_bonifications', function (Blueprint $table) {
            // Valor por unidad solicitado (antes del prorrateo) y total final facturado.
            // NULL en ambos = bonificacion clasica ($0, transmitida como pedido de obsequio).
            $table->decimal('invoice_unit_value', 14, 2)->nullable();
            $table->decimal('invoice_total_value', 14, 2)->nullable();
        });

        Schema::table('order_products', function (Blueprint $table) {
            // Rebaja neta por unidad de factura que absorbe el valor prorrateado del obsequio.
            $table->decimal('gift_deduction_per_unit', 14, 2)->default(0);
        });

        Schema::table('orders', function (Blueprint $table) {
            // Auditoria: decisiones descuento vs obsequio, prorrateo y avisos.
            $table->json('express_gift_audit')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('express_gift_audit');
        });
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn('gift_deduction_per_unit');
        });
        Schema::table('order_product_bonifications', function (Blueprint $table) {
            $table->dropColumn(['invoice_unit_value', 'invoice_total_value']);
        });
        Schema::table('bonifications', function (Blueprint $table) {
            $table->dropColumn('gift_invoice_unit_value');
        });
    }
};
