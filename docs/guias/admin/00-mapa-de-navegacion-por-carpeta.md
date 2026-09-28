# Guía: panel de administración (mapa de módulos)

Vista de conjunto del back-office alineada con `routes/admin.php`. Detalle operativo en las guías numeradas de esta carpeta.

## Navegación

- Tras `auth` + `role:admin`, el dashboard suele redirigir al catálogo (`products.index`).
- Bloques típicos de menú: **Catálogo e inventario**, **Clientes y vendedores**, **Ventas y reportes**, **Promociones**, **Contenido**, **Ajustes**.

## Catálogo, precios e inventario

| Recurso o prefijo | Qué hace | Guía |
|-------------------|----------|------|
| `products` (+ `updateproductprices`) | Productos, imágenes, precios; sync Dynamics por SKU de producto **y** de variación | [01](./01-gestion-de-catalogo-producto-y-medios.md) |
| `categories`, `brands`, `vendors`, `labels`, `tags`, `variations` | Taxonomía y variaciones | [01](./01-gestion-de-catalogo-producto-y-medios.md) |
| `settings` / inventario / `inventory-logs` | Inventario, mínimo global, sync, aviso bodegas no sync ayer | [05](./05-inventario-bodegas-y-sincronizacion.md), [14](./14-configuracion-ajustes-modo-vacaciones-correo.md) |
| `shipping-methods` | Métodos + **habilitación por ciudad** | [18](./18-festivos-impuestos-envio-y-retenciones.md) |
| `holidays`, `delivery-calendars`, `route-cycles` | Festivos, calendarios, ciclos | [13](./13-calendarios-entrega-y-envio.md), [18](./18-festivos-impuestos-envio-y-retenciones.md) |
| `bulk-operations` | Sincro masiva clientes | [15](./15-operations-masivas-sincro-clientes.md) |
| `taxes` | Impuestos | [18](./18-festivos-impuestos-envio-y-retenciones.md) |

## Promociones y marketing

| Recurso | Uso | Guía |
|---------|-----|------|
| `promociones`, `volume-discounts`, `promocion` | Hub y descuentos por volumen/precio | [06](./06-centro-de-promociones-precio-y-volumen.md), [07](./07-descuentos-y-promociones-englobado.md) |
| `bonifications` | Compra X lleva Y; mensajes de stock de obsequio en checkout | [08](./08-bonificaciones.md) |
| `coupons` (+ `search-zones`, mass-create, export) | Cupones, zonas AJAX, stacking marca/proveedor | [09](./09-cupones-gestion-avanzada.md) |
| `coupon-tests` | Diagnóstico técnico | [17](./17-coupon-tests-solo-tecnicos.md) |
| `banners`, `featured-*`, `content`, `content-pages`, `upsell-*` | Contenido en sitio | [11](./11-contenido-banners-destacados-campanas-upsell.md) |
| `admin.campaigns` | Hub: auto-tags y título de destacados | [11](./11-contenido-banners-destacados-campanas-upsell.md) |
| `retentions`, `email-templates` | Retenciones y plantillas | [18](./18-festivos-impuestos-envio-y-retenciones.md), [11](./11-contenido-banners-destacados-campanas-upsell.md) |

## Personas, pedidos y reportes

| Recurso | Uso | Guía |
|---------|-----|------|
| `users`, `sellers`, `admins` | Cuentas, 48h, zonas | [10](./10-usuarios-vendedores-y-accesos.md) |
| `orders`, `exports` | Pedidos, reintentos XML/email, exportes | [04 b2b](../b2b-tienda/04-carrito-checkout-y-ordenes.md), [16](./16-reportes-kpi-y-exportes.md) |
| `reports`, `reports/daily-sales`, `reports/vendor-sales` | Reportes bajo demanda, ventas día, **ventas por proveedor** (cola) | [16](./16-reportes-kpi-y-exportes.md) |
| `contacts`, `kpi` | Leads / KPI | [16](./16-reportes-kpi-y-exportes.md) |

## Fuera del panel admin (pero operativos)

| Tema | Guía |
|------|------|
| Mi Cuenta / Mi Ruta / Mis Zonas / Direcciones por rol | [roles/03](../roles/03-mi-cuenta-pestanas-y-visibilidad.md) |
| `/cliente-nuevo`, magic link, Tronex | [b2b/03](../b2b-tienda/03-registro-alta-cuenta-tronex-y-sesion.md) |
| Carrito Entrega Standard / Especial | [b2b/04](../b2b-tienda/04-carrito-checkout-y-ordenes.md), [b2b/05](../b2b-tienda/05-plazos-entrega-vista-comprador.md) |
| Zonas 48h / Coordinadora | [12](./12-zonas-rutas-y-48-horas.md) |

## Ajustes globales frecuentes

Express 48h, envío gratis especial, forzar fecha, inventario mínimo global, modo vacaciones, colas — [14](./14-configuracion-ajustes-modo-vacaciones-correo.md).

## Buenas prácticas

- Operaciones destructivas solo con formación ([DANGEROUS_MIGRATIONS.md](../../DANGEROUS_MIGRATIONS.md)).
- *Coupon-tests* y *bulk* preferible en *stage*.

---

**Revisado:** septiembre 2026
