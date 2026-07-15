# Integración: Dynamics (SOAP), colas, pedidos, Mailgun y Coordinadora

Capa de *transporte* y *jobs* que conecta el *checkout* web a **Microsoft Dynamics AX** (SOAP) y, en paralelo, a **envío Coordinadora** y al **envío de correo Mailgun**. No reemplaza el análisis del código; orienta a *NOC* y a desarrolladores nuevos.

## 1. Puntos de integración (mapa)

| Sistema / paquete | Dónde en código | Comportamiento resumido |
|-------------------|-----------------|-------------------------|
| **Microsoft Dynamics (precios)** | `App\Jobs\UpdateProductPrices` | SOAP *getPriceAndDiscount*; parseo XML; actualiza precios y, opcionalmente, variaciones. |
| **Microsoft Dynamics (inventario)** | `App\Jobs\SyncProductInventory` | Operación SOAP de existencia por bodega; *logs* de cuerpos o errores. |
| **Microsoft Dynamics (dimensiones)** | `App\Jobs\SyncProductDimensions` | SOAP *ObtenerArticulos* (`docs/dimensiones y peso.pdf`); sincroniza peso/alto/ancho/largo a `products.coordinadora_*`; nunca sobrescribe con ceros; *log* en `product_dimension_sync_logs`; comando `products:sync-dimensions`. |
| **Tamaños de empaque (Coordinadora)** | `PackageAssignmentService`, modelo `PackageType`, panel `package-types` | Asigna el empaque más pequeño que cubra volumen/peso del pedido (o múltiplos del mayor); se envía como `empaques` en la guía y se persiste en `orders.coordinadora_packages`. |
| **Pedido / presales (XML)** | `OrderRepository` + `ProcessOrder` / `ProcessOrderAsync` | Reintento, almacenamiento de *request*/*response*; en admin, `orders.retry-xml-transmission`. |
| **Coordinadora (envío)** | `CoordinadoraOrderProcessingService`, `CoordinadoraQuoteService`, *guides* | Express 48h; cotización en `GET /api/shipping-quote/{method}`. |
| **Correo** | `MailingService` + Mailgun (Symfony) | Ajustes vía *settings*; prueba con ruta *admin* `test.email`. |
| **Factura FV (Dynamics 365 F&O)** | `FvDynamicsService` | SOAP `CreateSalesOrder` en `DYNPRODWSSalesForceGroup` (ver `docs/fv.pdf`); usado en el flujo Coordinadora 48h. |

## 2. Colas y Horizon

Jobs pesados típicos: imágenes, `ProcessOrderAsync`, `SyncProductInventory`, `UpdateProductPrices`, *suite* de cupones, operaciones *bulk* de Rutero. Driver habitual **Redis** + **Horizon**. Ver [colas-y-horizon.md](colas-y-horizon.md) y los *README* en inglés bajo `docs/`.

## 3. Tronex (nombre de negocio)

*Tronex* no es un *client* en `app/Services`: se usa como **método de entrega** y flujo de **migración de usuario** (rutas `web` `/tronex/…`, constantes en *Order* y cálculo de plazos). Guía de usuario: [Tronex y sesión en la tienda B2B](../guias/b2b-tienda/03-registro-alta-cuenta-tronex-y-sesion.md).

## 4. Runbooks detallados (inglés)

- [../ORDER_RETRY_SYSTEM.md](../ORDER_RETRY_SYSTEM.md)  
- [../ORDER_RETRY_QUICK_START.md](../ORDER_RETRY_QUICK_START.md)  
- [../EMAIL_TROUBLESHOOTING.md](../EMAIL_TROUBLESHOOTING.md)  
- [../MAILGUN.md](../MAILGUN.md)  

## 5. Secretos y seguridad

- No commitear `.env` con credenciales *Dynamics*, Mailgun ni *Coordinadora*.  
- Rotar *SOAP* y *API* keys según la política interna.

---

*Revisión: abril 2026.*
