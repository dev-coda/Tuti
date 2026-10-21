<div class="grid grid-cols-6 gap-6">
    {{ Aire::input('name', 'Nombre')->groupClass('col-span-6') }}

    {{ Aire::select($giftProducts ?? $products, 'product_id', 'Producto obsequio')->groupClass('col-span-6 sm:col-span-3') }}

    {{ Aire::input('gift_quantity', 'Unidades del obsequio')->value(old('gift_quantity', isset($spendingGift) ? $spendingGift->gift_quantity : 1))->groupClass('col-span-3')->helpText('Se entregan una sola vez por pedido, no por cada producto.') }}

    {{ Aire::input('minimum_amount', 'Monto mínimo (con IVA)')->groupClass('col-span-3')->helpText('Suma con IVA de los productos asociados. Al alcanzarla en un pedido Express se entrega este obsequio.') }}

    {{ Aire::input('gift_invoice_unit_value', 'Valor mínimo facturable del obsequio (Express)')->groupClass('col-span-3')->helpText('Valor neto por unidad (sin IVA) con el que se factura. Vacío = valor por defecto (' . number_format((float) config('express_gifts.default_min_unit_value'), 2) . ').') }}

    {{ Aire::select(['cart' => 'Todo el carrito sin descuento', 'trigger' => 'Solo los productos que suman para este obsequio'], 'gift_proration_scope', 'Alcance del prorrateo (Express)')->value(old('gift_proration_scope', isset($spendingGift) ? $spendingGift->gift_proration_scope : 'cart'))->groupClass('col-span-6')->helpText('El valor facturado del obsequio se descuenta de productos sin descuento para que el total de la factura coincida con el pedido.') }}

    <div class="col-span-6">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" class="rounded border-gray-300" @checked(old('active', isset($spendingGift) ? $spendingGift->active : true))>
            Activo
        </label>
    </div>
</div>
