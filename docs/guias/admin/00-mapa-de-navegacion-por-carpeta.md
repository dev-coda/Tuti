# Guía: panel de administración (rol `admin`)

Vista de conjunto de los **módulos** del back-office y sus usos, alineada con [admin.php](../../routes/admin.php) y nombres de ruta. Para detalle de reglas y campos, enlazar con los manuales de dominio: [configuracion.md](./configuracion.md), [catalogo-productos.md](./catalogo-productos.md), [inventario.md](./inventario.md), [cupones.md](./cupones.md), etc.

## Navegación y punto de entrada

- Tras `auth` + `role:admin`, el **dashboard** (`/dashboard`) redirige a `products.index` (catálogo) salvo otra lógica en vuestro menú.
- Estructurar en la UI: **Catálogo e inventario**, **Clientes y vendedores**, **Ventas y reportes**, **Promociones**, **Contenido y marketing**, **Ajustes del sistema**.

> Las URLs exactas siguen el patrón `/recurso` o `/prefijo/...` según las líneas de [admin.php](../../routes/admin.php). Si el menú aplica *prefix* global (p. ej. `admin/`), añadid el prefijo que use vuestra `RouteServiceProvider` / URL del servidor.

## Catálogo, precios e inventario

| Recurso o prefijo | Qué hace (resumen) |
|-------------------|---------------------|
| `products` | Productos, imágenes, reorden, combinaciones, exportación, actualización de precios masiva (`updateproductprices`) |
| `categories` y `categories/{id}/highlights` | Categorías y productos **destacados** por categoría |
| `brands` | Marcas (proveedores) |
| `vendors` | **Proveedores** raíz |
| `labels` | Etiquetas (filtros, SEO, vitrinas) |
| `tags` y toggles (auto *nuevo*, *descuento*) | **Etiquetas** de producto y reglas automáticas |
| `variations` / `variations.items` | **Variaciones** y posiciones/ítems de variación |
| `settings` (subsecciones) y `settings/ventas` | Ajuste global, inventario, vacaciones, reenviar pedidos, mailer, ventas |
| `settings/zone-warehouses` (sync) | Mapeo **bodega ↔ zona** |
| `settings/sync-inventory`, `inventory-logs` | Sincro de inventario y trazas |
| `shipping-methods` | Métodos de entrega, activación, edición |
| `holidays` (import, export) | Días no laborables o especiales en calendario de entregas |
| `delivery-calendars` (+ import) | **Calendarios** de días/ventanas de entrega |
| `route-cycles` (+ import) | **Ciclos de ruta** |
| `bulk-operations` | Sincro masiva de datos de clientes y **reportes** generados (descargar, borrar) |
| `taxes` | **Impuestos** |

Búsquedas: ver documentación de API para integraciones; en UI, usad filtros y exportaciones CSV/Excel de cada módulo cuando exista.

## Promociones, precios y marketing transaccional

| Recurso o prefijo | Uso |
|-------------------|-----|
| `promociones` (index: hub) | Página central de *promociones* (enlaces a descuento directo, volumen, bonificaciones, cupones, análisis) |
| `volume-discounts` | **Descuentos por volumen** (reglas y tramos) |
| `promocion` (resource) | Tipo *promoción* (según vuestro modelo) |
| `bonifications` | [Bonificaciones “compra X, lleva Y”](./bonificaciones.md) |
| `coupons` (export, mass-create, toggle) | [Cupones](./cupones.md) y operaciones en masa |
| `coupon-tests` | Módulo de **prueba/diagnóstico** de cupones (órdenes simuladas, inspección XML) — *uso técnico* |
| `banners` | Banners (tipos, secuencia) |
| `featured-products`, `featured-categories` | **Destacados** en inicio, carruseles, títulos |
| `content` (claves) | **Contenido estático** (claves reutilizables) |
| `content-pages` | Páginas con **slug** (`/contenido/{slug}`) [contenido-banners-campanas.md](./contenido-banners-campanas.md) |
| `upsell-zones`, `upsell-rules` | Zonas de *cross/upsell* y reglas |
| `retentions` | **Retención** o reglas retenidas (según modelo) |
| `admin.campaigns` (index + settings) | **Campañas** (activación, parámetros) |

También: [Plantillas de correo](../EMAIL_TEMPLATES.md) (detalle técnico en repositorio); en la app: ruta `admin.email-templates.*`.

## Personas, pedidos y comunicación

| Recurso | Uso |
|---------|-----|
| `users` (patch zona 48h) | [Usuarios](./usuarios-autenticacion.md), 48h Coordinadora, export |
| `sellers` y export vendedor | Gestionar vendedores |
| `admins` | Cuentas administradoras del panel |
| `orders` (resend, reintentar XML, reintentar emails, exporte mensual) | [Órdenes](./carrito-ordenes.md#gestión-de-órdenes-administradores) |
| `exports` (listado, download, status) | Colas de **exportes** mensuales/ masivos de órdenes |
| `reports` (generate, download) | **Reportes** bajo demanda; [reportes-y-exportaciones.md](./reportes-y-exportaciones.md) |
| `reports/daily-sales` y export | [reportes-y-exportaciones.md](./reportes-y-exportaciones.md) |
| `contacts` y `contactexport` | Formularios o leads en **Contact** |
| `kpi` (KpiController) | [reportes-y-exportaciones.md](./reportes-y-exportaciones.md) (dashboard KPI y export) |

**Perfil** del proveedor (genérico): ruta de perfil `profile.update` apuntando a `VendorController@index` en el fragmento visto; confirmar con el menú *Perfil*.

**Acción de prueba de correo:** ruta con nombre `test.email` (POST) para probar el mailer (Mailgun) desde el panel, según vuestro formulario en Ajustes.

## Impuestos, entrega e inventario mínimo

- **Express 48h**, forzar fecha de entrega, inventario mínimo global, modo vacaciones, procesar pedidos en cola, etc. — formularios en [configuracion.md](./configuracion.md) bajo *settings* (POST documentados en admin).

## Exportaciones y auditoría (referencia)

- `orderauditexport` — descarga vinculada a auditoría de pedidos. Véase la documentación técnica (p. ej. *Daily audit* en [../tecnica/README.md](../tecnica/README.md)) para runbooks en inglés; el uso operativo es **descargar** el informe y revisar discrepancias.

## Buenas prácticas de seguridad

- Operaciones destructivas (migraciones, borrado) solo por personal con formación. Ver [DANGEROUS_MIGRATIONS.md](../DANGEROUS_MIGRATIONS.md) en documentación técnica.
- *Coupon-tests* y *bulk* pueden afectar datos: usar en *stage* o con supervisión.

---

**Revisado:** Abril 2026
