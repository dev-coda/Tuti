# Plazos y entregas: vista del comprador

Público: tenderos y compradores. **Configuración** (calendarios, ciclos, festivos, 48h, ajuste global) está documentada en:

- [13 — Calendarios y envío (admin)](../admin/13-calendarios-entrega-y-envio.md)
- [12 — Zonas, rutas, 48h (admin)](../admin/12-zonas-rutas-y-48-horas.md)
- [14 — Modo vacaciones y ajustes (admin)](../admin/14-configuracion-ajustes-modo-vacaciones-correo.md)

Esta nota concentra qué *ve* el usuario y *qué APIs públicas* usa el *frontend*; no repite el manual de carga de CSV de administración.

## 1. Qué determina el “día aproximado de llegada”

1. **Método de entrega** (programado ruta *vs.* express, según oferta y constantes de negocio en el modelo de pedido y la pantalla de checkout).  
2. **Zona** (sesión: `zone_id` al autenticar o al cambiar; el repositorio de fechas toma en cuenta la zona; si el usuario queda sin zona, el cálculo falla o degrada con mensaje).  
3. **Calendarios y ciclos** (A, B, C) y días *no* hábiles: los admin los mantienen; el cálculo los consume.  
4. **Modo vacaciones:** el cliente puede recibir *mensaje* o bloqueo de carrito según `Setting` — el front a menudo consulta `GET /api/vacation-mode`.

## 2. Cálculo mostrado en el navegador

**Endpoint:** `GET {APP_URL}/api/delivery-date/{method}`

- La zona de trabajo se obtiene de: usuario con sesión (por ejemplo `zone_id` en *session*) o, si se está ajustando la entrega, del parámetro de consulta `zone_id` según implementa `routes/api.php` y el *closure* alrededor de `OrderRepository::getDeliveryDateByMethod($method, $zone)`.
- **Respuesta típica:** un objeto con la fecha *textual* en castellano para mostrar (día de la semana, número, mes) y, según el código, `raw_date` para cálculo interno.

*Importante:* un cambio de zona en *checkout* debe reintentar el *fetch* de `delivery-date` o el usuario quedará con una *fecha* obsoleta.

## 3. Envío *express* y cotización (0 o coste variable)

**Endpoint:** `GET /api/shipping-quote/{method}`

- Si el método y la zona *no* activan lógica de Coordinadora (por ejemplo, express desactivada globalmente o la zona *no* usa 48h con Transportadora), el JSON puede devolver proveedor interno “Tronex” y **coste cero**.  
- Si aplica, el servicio `CoordinadoraQuoteService` cota a partir de la **línea de carrito** (sesión) y de la *zona*. Errores de cotización: HTTP 422 con *message*; soporte mira *logs* y *API keys*; ver [integración técnicas](../tecnica/README.md).  
- No asegurar a un *tendero* *precio* de flete o *día* sin replicar *su* *zona* y *método* en *pruebas* *stage*.

## 4. Estados y “diferir transmisión” hacia el ERP (lectura sencilla)

- La orden creada puede ponerse *en cola* y terminar *procesada*, *en error*, o *en espera* mientras *no* llega *la ventana* de corte.  
- El *significado* exacto de *cada* *status* (entero o etiqueta) está en [04 — Carrito, checkout y órdenes](./04-carrito-checkout-y-ordenes.md#-estados-de-orden) y, para *texto* mostrado al *usuario* final, *copiar* la **traducción** o el *color* usado en las vistas `orders` o `clients/orders`.

## 5. *Runbook* corto para el soporte (“la fecha *no* cuadra”)

1. Confirmar **zona** y **ciclo** del cliente; si vienen de *Rutero*, ejecutar [operaciones masivas (admin)](../admin/15-operations-masivas-sincro-clientes.md) o reenviar a operaciones.  
2. Revisar **calendarios y route-cycles** *del año/periodo* actual (import mal fechado es la causa nº1).  
3. Revisar **festivos** y sábados *laborables* en días inusuales.  
4. Replicar *pedido* de *prueba* *en* *stage* *con* *el* *mismo* *`zone_id`* y *`method`*; *nunca* *prometer* *cambio* *de* *código* *a* *quien* *no* *gestiona* *deploy*.

## 6. Referencia cruzada técnica (sin mantener nombres de *commit*)

- `routes/api.php` — cierres `delivery-date`, `shipping-quote`, `vacation-mode`.  
- [API — referencia completa (HTTP)](../tecnica/api-referencia-completa.md) — *tabla* *misma* *sección*.

---

*Revisión: abril 2026.*
