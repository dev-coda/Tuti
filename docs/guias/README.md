# Guías Tuti: índice general

Bienvenido. Esta carpeta agrupa el material **por audiencia**, en **español**. El contenido se alinea con código (`routes/*.php`, controladores, jobs).

**Lectura en el panel (rol *admin*):** menú lateral *Documentación* o ruta `/documentacion` — se muestran **los mismos** archivos que en `docs/guias/`.

## Cómo usar este índice

1. Elegí el **perfil** (abajo) y abrí los documentos de esa columna, en orden.
2. Términos (“ruta A/B/C”, “Rutero”, “Tronex”, “48h”, “Entrega Especial”, “Mi Ruta”): buscá en **Administración**, **B2B tienda** o **Roles**.
3. Desarrolladores: **Documentación técnica** al final.

---

## Mapa de carpetas

| Carpeta | Público | Contenido |
| -------- | ------- | --------- |
| [b2b-tienda](./b2b-tienda/) | Compradores, tenderos, comercial | Compra, producto, carrito, plazos, registro / cliente nuevo / magic link |
| [admin](./admin/) | Administración, operación, supervisor (contexto) | Catálogo, inventario, promociones, calendario, envío, reportes (incl. ventas proveedor) |
| [roles](./roles/) | Vendedor, supervisor, tendero | Sesión seller, **Mi Cuenta** (pestañas), módulo `/tendero` |

**Archivado (no mantener a la par):** [../manuales-archivados/](../manuales-archivados/).

---

## B2B: tienda y flujo de compra (`b2b-tienda/`)

| # | Archivo | Tema |
| --- | ------- | ---- |
| 0 | [00-introducción…](./b2b-tienda/00-introduccion-negocio-roles-b2b-y-terminologia.md) | Ecosistema, roles, glosario |
| 1 | [01-visión…](./b2b-tienda/01-vision-general-rutas-y-flujos.md) | Rutas, invitado vs autenticado, carrito API |
| 2 | [02-catálogo…](./b2b-tienda/02-catalogo-producto-y-buscador.md) | Categoría, ficha, búsqueda, stock por variación |
| 3 | [03-registro…](./b2b-tienda/03-registro-alta-cuenta-tronex-y-sesion.md) | Formulario, `/cliente-nuevo`, magic link (código 6 dígitos), Tronex, reset |
| 4 | [04-carrito…](./b2b-tienda/04-carrito-checkout-y-ordenes.md) | Pedido, **Entrega Standard/Especial**, inventario y bonificaciones |
| 5 | [05-plazos…](./b2b-tienda/05-plazos-entrega-vista-comprador.md) | Fechas en carrito, cotización, zona/ciudad |

---

## Roles (`roles/`)

| # | Archivo | Tema |
| --- | ------- | ---- |
| 1 | [01-vendedor…](./roles/01-vendedor-rol-seller.md) | Cliente activo, Mi Cuenta, origen RUTA |
| 2 | [02-tendero…](./roles/02-tendero-interfaz-shopper.md) | Prefijo `/tendero` |
| 3 | [03-mi-cuenta…](./roles/03-mi-cuenta-pestanas-y-visibilidad.md) | Pestañas por rol, Mi Ruta / Mis Zonas, sin Direcciones para seller/supervisor |

---

## Administración (`admin/`)

| Rango / archivo | Tema |
| --------------- | ---- |
| [00-mapa…](./admin/00-mapa-de-navegacion-por-carpeta.md) | Índice de módulos → guías |
| [01-catálogo…](./admin/01-gestion-de-catalogo-producto-y-medios.md) | Productos; sync precios por SKU de variación |
| [05-inventario…](./admin/05-inventario-bodegas-y-sincronizacion.md) | Bodegas, safety stock, reintentos / bodegas sin sync ayer |
| [06](./admin/06-centro-de-promociones-precio-y-volumen.md)–[09](./admin/09-cupones-gestion-avanzada.md) | Promos, bonos, cupones (zonas AJAX, stacking) |
| [10](./admin/10-usuarios-vendedores-y-accesos.md)–[11](./admin/11-contenido-banners-destacados-campanas-upsell.md) | Usuarios; banners/campañas (auto-tags) |
| [12](./admin/12-zonas-rutas-y-48-horas.md)–[14](./admin/14-configuracion-ajustes-modo-vacaciones-correo.md) | Zonas 48h, calendarios, ajustes (envío gratis) |
| [15](./admin/15-operations-masivas-sincro-clientes.md)–[16](./admin/16-reportes-kpi-y-exportes.md) | Bulk; KPI, daily sales, **vendor sales** en cola |
| [17](./admin/17-coupon-tests-solo-tecnicos.md)–[18](./admin/18-festivos-impuestos-envio-y-retenciones.md) | Coupon-tests; festivos, métodos por ciudad |

---

## Técnica (fuera de `guias/`)

| Documento | Uso |
| --------- | --- |
| [../tecnica/](../tecnica/README.md) | Índice técnica |
| [../tecnica/api-referencia-completa.md](../tecnica/api-referencia-completa.md) | Endpoints |
| [../tecnica/integracion-erp-soap-cola-y-correo.md](../tecnica/integracion-erp-soap-cola-y-correo.md) | Dynamics, Mailgun, Coordinadora |
| [../tecnica/despliegue.md](../tecnica/despliegue.md), [colas-y-horizon.md](../tecnica/colas-y-horizon.md) | Operación |

## Convención de mantenimiento

- Cambio de ruta o regla de negocio → actualizar el `.md` de `guias/` que corresponda (el módulo Documentación lo sirve tal cual).
- No inventar features: documentar solo lo presente en código de la rama desplegada.
- Módulo nuevo en admin → subcapítulo + enlace en este README y en `00-mapa`.

*Última actualización de contenido operativo: septiembre 2026.*
