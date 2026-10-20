<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prorrateo Express en vivo</title>
    <style>
        body { margin: 0; font-family: "Segoe UI", sans-serif; background: #f4f6f8; color: #1f2933; }
        header { background: #0f2744; color: #fff; padding: 28px 32px; }
        header p { margin: 8px 0 0; color: #d6deea; }
        main { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .cart, .card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h2 { margin: 0 0 8px; font-size: 20px; }
        .muted { color: #52606d; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e4e7eb; }
        th { font-size: 12px; text-transform: uppercase; color: #7b8794; }
        .money { text-align: right; font-variant-numeric: tabular-nums; }
        .ok { color: #0e7c3a; font-weight: 700; }
        .bad { color: #b42318; font-weight: 700; }
        .tag { display: inline-block; background: #fff4e5; color: #9a3412; border-radius: 999px; padding: 2px 8px; font-size: 12px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .total { display: flex; justify-content: space-between; margin-top: 12px; font-size: 16px; }
        @media (max-width: 800px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>
    <h1>Prorrateo del obsequio Express</h1>
    <p>Mismos productos y la misma regla (compra 10, lleva 1). El cálculo lo hace el motor real del checkout. Recarga la página para verlo de nuevo.</p>
</header>
<main>
    <section class="cart">
        <h2>Pedido de ejemplo</h2>
        <p class="muted">IVA 19% en todas las líneas. El obsequio se factura a $500 netos (valor mínimo), no a $0.</p>
        <table>
            <thead>
                <tr><th>Producto</th><th>Papel</th><th class="money">Cantidad</th><th class="money">Precio unitario</th></tr>
            </thead>
            <tbody>
                @foreach($lines as $line)
                    <tr>
                        <td>{{ $line['name'] }}</td>
                        <td>{{ $line['role'] }}</td>
                        <td class="money">{{ $line['qty'] }}</td>
                        <td class="money">${{ number_format($line['unit_price'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td>{{ $gift['name'] }}</td>
                    <td>{{ $gift['rule'] }} · valor mínimo facturable</td>
                    <td class="money">{{ $gift['qty'] }}</td>
                    <td class="money">${{ number_format($gift['unit_value'], 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
        <div class="total"><span>Total del pedido antes del obsequio (con IVA)</span><strong>${{ number_format($before, 2, ',', '.') }}</strong></div>
    </section>

    <div class="grid">
        @foreach(['cart' => ['Todo el carrito sin descuento', $cart], 'trigger' => ['Solo el producto que activa la bonificación', $trigger]] as $key => [$title, $scenario])
            <section class="card">
                <h2>{{ $title }}</h2>
                @if($scenario['feasible'])
                    <p class="ok">Se aplicó el obsequio. La factura cuadra.</p>
                @else
                    <p class="bad">{{ $scenario['reason'] }}</p>
                @endif
                <table>
                    <thead>
                        <tr><th>Línea</th><th class="money">Antes</th><th class="money">Rebaja</th><th class="money">Después</th></tr>
                    </thead>
                    <tbody>
                        @foreach($scenario['rows'] as $row)
                            <tr>
                                <td>{{ $row['name'] }} @if($row['touched'])<span class="tag">prorrateado</span>@endif</td>
                                <td class="money">${{ number_format($row['before'], 2, ',', '.') }}</td>
                                <td class="money">{{ $row['deduction'] > 0 ? '-$'.number_format($row['deduction'], 2, ',', '.') : '$0,00' }}</td>
                                <td class="money">${{ number_format($row['after'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td>Taza obsequio (facturada)</td>
                            <td class="money">—</td>
                            <td class="money">—</td>
                            <td class="money">${{ number_format($scenario['gift_net'], 2, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="total"><span>Total factura con IVA</span><strong>${{ number_format($scenario['after'], 2, ',', '.') }}</strong></div>
                <p class="muted">Diferencia contra el pedido original: ${{ number_format($scenario['after'] - $before, 2, ',', '.') }}</p>
            </section>
        @endforeach
    </div>

    <section class="card">
        <h2>Si el obsequio no cabe</h2>
        <p>Misma compra 10 lleva 1, pero el valor mínimo se fuerza a $100.000 y el alcance es solo el café. Ese valor dejaría el café en cero.</p>
        @if($blocked['feasible'])
            <p class="ok">Se pudo aplicar.</p>
        @else
            <p class="bad">{{ $blocked['reason'] }}</p>
            <p class="muted">En el checkout real no se entrega el obsequio y el producto conserva su descuento, si lo tenía.</p>
        @endif
    </section>

    <section class="cart">
        <h2>Obsequio por monto, solo Express</h2>
        <p class="muted">Otra compra. Cada regla tiene su propio regalo y su propio mínimo. El regalo se entrega una sola vez cuando la suma con IVA de sus productos llega al monto. En Tronex estas reglas no corren.</p>
        <table>
            <thead>
                <tr><th>Producto</th><th class="money">Cantidad</th><th class="money">Precio unitario</th><th class="money">Con IVA</th></tr>
            </thead>
            <tbody>
                @foreach($spendingLines as $line)
                    <tr>
                        <td>{{ $line['name'] }}</td>
                        <td class="money">{{ $line['qty'] }}</td>
                        <td class="money">${{ number_format($line['unit_price'], 0, ',', '.') }}</td>
                        <td class="money">${{ number_format($line['qty'] * $line['unit_price'] * 1.19, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="total"><span>Total del pedido con IVA</span><strong>${{ number_format($spendingBefore, 2, ',', '.') }}</strong></div>
    </section>

    <section class="card">
        <h2>Tres reglas, regalos distintos</h2>
        <table>
            <thead>
                <tr><th>Regla</th><th>Productos que suman</th><th>Obsequio</th><th class="money">Llevan</th><th class="money">Mínimo</th><th>Resultado</th></tr>
            </thead>
            <tbody>
                @foreach($rules as $rule)
                    <tr>
                        <td>{{ $rule['name'] }}</td>
                        <td>{{ $rule['products'] }}</td>
                        <td>{{ $rule['gift'] }}</td>
                        <td class="money">${{ number_format($rule['spent'], 0, ',', '.') }}</td>
                        <td class="money">${{ number_format($rule['minimum'], 0, ',', '.') }}</td>
                        <td class="{{ $rule['granted'] ? 'ok' : 'bad' }}">{{ $rule['granted'] ? 'Se entrega 1 vez' : 'No alcanza' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Factura Express de los obsequios que sí se ganaron</h2>
        @if($spendingInvoice['feasible'])
            <p class="ok">La taza y el termo entran a la factura. La cuchara no, porque el azúcar no llegó a $50.000. El total de la factura sigue igual al del pedido.</p>
        @else
            <p class="bad">{{ $spendingInvoice['reason'] }}</p>
        @endif
        <table>
            <thead>
                <tr><th>Línea</th><th class="money">Antes</th><th class="money">Rebaja</th><th class="money">Después</th></tr>
            </thead>
            <tbody>
                @foreach($spendingInvoice['rows'] as $row)
                    <tr>
                        <td>{{ $row['name'] }} @if($row['touched'])<span class="tag">prorrateado</span>@endif</td>
                        <td class="money">${{ number_format($row['before'], 2, ',', '.') }}</td>
                        <td class="money">{{ $row['deduction'] > 0 ? '-$'.number_format($row['deduction'], 2, ',', '.') : '$0,00' }}</td>
                        <td class="money">${{ number_format($row['after'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                @foreach($spendingInvoice['gifts'] as $giftRow)
                    <tr>
                        <td>{{ $giftRow['name'] }} (facturado)</td>
                        <td class="money">—</td>
                        <td class="money">—</td>
                        <td class="money">${{ number_format($giftRow['net'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="total"><span>Total factura con IVA</span><strong>${{ number_format($spendingInvoice['after'], 2, ',', '.') }}</strong></div>
        <p class="muted">Diferencia contra el pedido original: ${{ number_format($spendingInvoice['after'] - $spendingBefore, 2, ',', '.') }}</p>
    </section>

    <section class="card">
        <h2>Cantidades que se suman entre productos</h2>
        <p>En la bonificación hay que marcar <strong>Sumar las cantidades de los productos asociados</strong>. Con compra 10, lleva 1:</p>
        <table>
            <thead>
                <tr><th>Compra</th><th>Unidades que cuentan</th><th>Regalito</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>6 de azúcar y 4 de café</td>
                    <td class="money">10</td>
                    <td class="ok">{{ $mixedGift }} obsequio</td>
                </tr>
                <tr>
                    <td>10 de azúcar, sin café</td>
                    <td class="money">10</td>
                    <td class="ok">1 obsequio</td>
                </tr>
                <tr>
                    <td>10 de azúcar y 10 de café</td>
                    <td class="money">20</td>
                    <td class="ok">1 obsequio si el máximo es 1. 2 si el máximo alcanza.</td>
                </tr>
            </tbody>
        </table>
        <p class="muted">Si la casilla está apagada, 6 y 4 no entregan nada: cada producto tiene que llegar a 10 por su cuenta.</p>
    </section>
</main>
</body>
</html>
