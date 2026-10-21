<?php

/*
|--------------------------------------------------------------------------
| Obsequios facturados y prorrateados (pedidos Express / Coordinadora 48h)
|--------------------------------------------------------------------------
|
| Solo aplica a pedidos que se facturan por FV (Express). El pedido Tronex
| tradicional conserva el comportamiento de bonificaciones existente.
|
*/

return [
    // Interruptor general. Si es false, Express se comporta como antes.
    'enabled' => (bool) env('EXPRESS_GIFTS_ENABLED', true),

    // Valor neto minimo (sin IVA) por unidad con el que se factura un obsequio
    // cuando la bonificacion no define su propio valor.
    'default_min_unit_value' => (float) env('EXPRESS_GIFTS_MIN_UNIT_VALUE', 1.00),

    // Despues del prorrateo, ninguna linea puede quedar por debajo de este
    // porcentaje de su precio unitario (mismo piso del 10% de los descuentos fijos).
    'line_floor_ratio' => (float) env('EXPRESS_GIFTS_LINE_FLOOR_RATIO', 0.10),

    // Diferencia maxima tolerada (en pesos) entre el valor facturado de los
    // obsequios y lo descontado a las lineas, por redondeo de centavos.
    'balance_tolerance' => (float) env('EXPRESS_GIFTS_BALANCE_TOLERANCE', 0.05),
];
