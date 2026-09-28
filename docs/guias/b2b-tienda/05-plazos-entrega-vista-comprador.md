# Plazos y entregas: vista del comprador

Público: tenderos y compradores. **Configuración** (calendarios, ciclos, festivos, 48h, ajuste global) está documentada en:

- [13 — Calendarios y envío (admin)](../admin/13-calendarios-entrega-y-envio.md)
- [12 — Zonas, rutas, 48h (admin)](../admin/12-zonas-rutas-y-48-horas.md)
- [14 — Modo vacaciones y ajustes (admin)](../admin/14-configuracion-ajustes-modo-vacaciones-correo.md)
- [18 — Festivos, impuestos y métodos de envío](../admin/18-festivos-impuestos-envio-y-retenciones.md)

Esta nota concentra qué *ve* el usuario y *qué APIs* usa el *frontend*; no repite el manual de carga de CSV de administración.

## 1. Qué determina el “día aproximado de llegada”

1. **Método de entrega** visible en el carrito:
   - **Entrega Standard** (código `tronex`) — programada según ruta.
   - **Entrega Especial** (código `express`) — rápida / 48h cuando aplica.
2. **Zona** del cliente (y toggles `shipping_standard_enabled` / `shipping_express_enabled` de esa zona).
3. **Ciudad** del cliente: en *Métodos de envío* se puede habilitar/deshabilitar cada método por ciudad.
4. **Calendarios y ciclos** (A, B, C) y días no hábiles.
5. **Modo vacaciones:** mensaje o bloqueo según `Setting`; el front consulta `GET /api/vacation-mode`.

Bajo cada tarjeta de método el carrito muestra la fecha estimada (“Calculando…” → texto en castellano) vía `delivery-date`.

## 2. Cálculo mostrado en el navegador

**Endpoint:** `GET /api/delivery-date/{method}`

- Zona de trabajo: sesión (`zone_id`) o parámetro de consulta `zone_id`.
- **Respuesta típica:** fecha textual en castellano y, según el código, `raw_date`.

*Importante:* un cambio de zona en checkout debe volver a pedir `delivery-date` o la fecha queda obsoleta.

## 3. Entrega Especial y cotización

**Endpoint:** `GET /api/shipping-quote/{method}` (sesión web autenticada / carrito).

- Si Express 48h está apagado globalmente, o la zona/ciudad no permiten especial, el método no se ofrece o no cotiza Coordinadora.
- Si aplica Coordinadora 48h en la zona, `CoordinadoraQuoteService` cotiza según carrito y zona. Errores: HTTP 422 con *message*.
- **Envío gratis:** en Ajustes se puede activar umbral (`express_free_shipping_enabled` + `express_free_shipping_min`); el carrito muestra “Envío gratis desde $…”.
- No prometer precio de flete ni día sin replicar la misma zona y método en pruebas.

## 4. Estados y transmisión al ERP (lectura sencilla)

- La orden creada puede quedar en cola y terminar procesada, en error, o en espera hasta la ventana de corte.
- Detalle de estados: [04 — Carrito, checkout y órdenes](./04-carrito-checkout-y-ordenes.md).

## 5. Runbook corto (“la fecha no cuadra”)

1. Confirmar **zona**, toggles de envío de zona y **ciudad** (método habilitado).
2. Revisar **calendarios** y **route-cycles** del periodo.
3. Revisar **festivos** / sábados laborables.
4. Replicar pedido de prueba en *stage* con el mismo `zone_id` y método.

## 6. Referencia cruzada

- `routes/web.php` / `api.php` — `delivery-date`, `shipping-quote`, `vacation-mode`.
- [API — referencia completa](../tecnica/api-referencia-completa.md).

---

*Revisión: septiembre 2026.*
