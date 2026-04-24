# Guías Tuti: índice general

Bienvenido. Esta carpeta reemplaza la estructura plana anterior y agrupa el material **por finalidad y audiencia**, en **español**. El contenido de fondo (carrito, órdenes, calendario, descuentos, etc.) se tomó de los manuales históricos y de **código** (`routes/*.php`, controladores, jobs) y se mantiene actualizable por capítulos.

**Lectura en el panel (rol *admin*):** menú lateral *Documentación* o ruta `/documentacion` — se muestran **los mismos** archivos que en `docs/guias/`.

## Cómo usar este índice

1. Elegí el **perfil** (abajo) y abrí solo los documentos de esa columna, en orden.
2. Si te enfrentás a un término (“ruta A/B/C”, “Rutero”, “Tronex”, “48h”, “reproceso XML”), buscá la sección equivalente en **Administración** o en **B2B tienda**.
3. Desarrolladores: para contratos API, colas, ERP y despliegue, seguí el enlace a **Documentación técnica** al final de este documento.

---

## Mapa de carpetas


| Carpeta                     | Público                                                       | Contenido                                                                                                                                                                                           |
| --------------------------- | ------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [b2b-tienda](./b2b-tienda/) | Compradores, tenderos, personal comercial (uso web)           | Flujo de compra, producto, carrito, plazos, con referencias a `routes/web.php` y vistas públicas                                                                                                    |
| [admin](./admin/)           | Administración, operación, *supervisor* de vendedor           | Módulo por módulo: catálogo, inventario, promociones, calendario, ajustes, reportes, etc. Cada ruta nombra el tipo de tarea, no un menú fijo: la **UI** puede evolucionar, las responsabilidades no |
| [roles](./roles/)           | Vendedor (*seller*) y módulo **tendero** (*shopper* separado) | Tareas y restricciones por rol, distinguídas de la *tienda* principal con URL `/`                                                                                                                   |


**Archivado (solo referencia, no se actualiza de forma proactiva):** [../manuales-archivados/](../manuales-archivados/) *snapshot* al reorganizar, abril 2026. El contenido *activo* son los `.md` dentro de `guias/`.

---

## B2B: tienda y flujo de compra (`b2b-tienda/`)

Números: orden de lectura sugerido; documentos 04 y 05 (calendario con perspectiva mixta) son los más extensos.


| #   | Archivo                                                                                                              | Tema                                                                                                                                        |
| --- | -------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| 0   | [00-introduccion-negocio-roles-b2b-y-terminologia](./b2b-tienda/00-introduccion-negocio-roles-b2b-y-terminologia.md) | Ecosistema Tuti, roles, URLs, glosario, advertencias (login, formularios, Tronex)                                                           |
| 1   | [01-vision-general-rutas-y-flujos](./b2b-tienda/01-vision-general-rutas-y-flujos.md)                                 | Pointers de ruta, invitado vs. autenticado, API JSON del carrito                                                                            |
| 2   | [02-catalogo-producto-y-buscador](./b2b-tienda/02-catalogo-producto-y-buscador.md)                                   | Categoría, ficha, etiqueta, búsqueda, relevancia                                                                                            |
| 3   | [03-registro…](./b2b-tienda/03-registro-alta-cuenta-tronex-y-sesion.md)                                              | `formulario`, `register`, magic link, recuperar contraseña, Tronex, completar perfil, middleware *pending*                                  |
| 4   | [04-carrito-checkout-y-ordenes](./b2b-tienda/04-carrito-checkout-y-ordenes.md)                                       | **Base operativa** del pedido, estados, reintentos, emails, reorden, jobs                                                                   |
| 5   | [05-plazos-entrega-vista-comprador](./b2b-tienda/05-plazos-entrega-vista-comprador.md)                               | Cómo ve el plazo, `/api/delivery-date/…`, y enlace a [admin/13 calendario](./admin/13-calendarios-entrega-y-envio.md) para la configuración |


> La **ficha y listados** y la **lógica** de mínimo de proveedor, cupones y mínimo global viven en `04` y, para operadores, se reflejan de nuevo en *admin* (cupones, inventario, etc.).

---

## Roles: vendedor y tendero (`roles/`)


| #   | Archivo                                                               | Tema                                                                                                         |
| --- | --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| 1   | [01-vendedor-rol-seller](./roles/01-vendedor-rol-seller.md)           | Fijar/quitar *cliente* de sesión, requerimientos del vendedor, dashboard JSON, lectura con `OrderController` |
| 2   | [02-tendero-interfaz-shopper](./roles/02-tendero-interfaz-shopper.md) | Rutas `/tendero/`* sin lógica en controlador, diferencia con carrito principal, cuándo usar                  |


---

## Administración (`admin/`)

Los números aproximados siguen un **orden lógico de negocio**, no de menú. Archivos 00, 10 y 11 son *mapa* o *cruzados*; el resto es módular.


| Rango / archivo                                                                   | Tema                                                                                                                                                                                                                                                            | Notas                                                                                                       |
| --------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| [00-mapa-de-navegacion-por-carpeta](./admin/00-mapa-de-navegacion-por-carpeta.md) | Mapea cada prefijo a controlador, para orientación interna; ya lo teníamos documentado, se mantiene como hoja de ruta                                                                                                                                           |                                                                                                             |
| [01-gestion-de-catalogo…](./admin/01-gestion-de-catalogo-producto-y-medios.md)    | Productos, variaciones, combinados, imágenes, *highlights* por categoría, export                                                                                                                                                                                |                                                                                                             |
| [06-centro-de-promociones-precio-y-volumen](./admin/06-centro-de-promociones-precio-y-volumen.md) | Hub *promociones*, `volume-discounts` y *Promocion* (recurso); *no* son cupones por código | Ver también 07–09 |
| 05, 12, 13, 14                                                                    | [Inventario](admin/05-inventario-bodegas-y-sincronizacion.md) · [Zonas 48h](admin/12-zonas-rutas-y-48-horas.md) · [Calendarios y envío](admin/13-calendarios-entrega-y-envio.md) · [Ajustes globales](admin/14-configuracion-ajustes-modo-vacaciones-correo.md) | Base logística; 13 duplica lenguaje *admin* del módulo calendario; b2b/05 hace de puente hacia el comprador |
| 07–09                                                                             | [Descuentos (superior)](admin/07-descuentos-y-promociones-englobado.md) · [Bonos](admin/08-bonificaciones.md) · [Cupones](admin/09-cupones-gestion-avanzada.md)                                                                                                 | Jerarquía de reglas, cupones, bonificaciones                                                                |
| 10, 11, 16                                                                        | [Usuarios, vendedores, admins, contactos](admin/10-usuarios-vendedores-y-accesos.md) · [Banners, CMS, campañas, upsell](admin/11-contenido-banners-destacados-campanas-upsell.md) · [Reportes, KPI, exportes](admin/16-reportes-kpi-y-exportes.md)              | Personas, marketing en sitio, cierres e informes                                                            |
| 15                                                                                | [Operaciones masivas (Rutero + CSV)](./admin/15-operations-masivas-sincro-clientes.md)                                                                                                                                                                          | Proceso, reporte, impacto *antes* del pedido                                                                |
| 17                                                                                | [Pruebas y diagnóstico de cupones (solo téc.)](./admin/17-coupon-tests-solo-tecnicos.md)                                                                                                                                                                        | *coupon-tests*, nunca sustituyen pedidos reales; enlaces a *runbook* de tests                               |
| 18 | [Festivos, impuestos, envío y retenciones](./admin/18-festivos-impuestos-envio-y-retenciones.md) | `holidays`, `taxes`, `shipping-methods`, `retentions` (maestros y fisco) |


*Recursos* ya cubiertos: **holidays, taxes, shipping-methods, retentions** → [18](admin/18-festivos-impuestos-envio-y-retenciones.md). El **hub** *promociones* y **volume** / entidad *Promocion* → [06](admin/06-centro-de-promociones-precio-y-volumen.md). Lo demás: [00](admin/00-mapa-de-navegacion-por-carpeta.md), [DATABASE.md](../DATABASE.md), `routes/admin.php`.

---

## Técnica, API y despliegue (fuera de `guias/`)


| Ruta al documento                                                                                          | Uso                                                                             |
| ---------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| [../tecnica/](../tecnica/README.md)                                                                        | Índice maestro técnica + enlaces a *fixes* y DB                                 |
| [../tecnica/api-referencia-completa.md](../tecnica/api-referencia-completa.md)                 | Todos los endpoints con método, *middleware*, y propósito (Sanctum vs. público) |
| [../tecnica/integracion-erp-soap-cola-y-correo.md](../tecnica/integracion-erp-soap-cola-y-correo.md)       | Microsoft Dynamics, Mailgun, Coordinadora, jobs                                 |
| [../tecnica/despliegue.md](../tecnica/despliegue.md) y [colas-y-horizon.md](../tecnica/colas-y-horizon.md) | Operación de servidor y colas                                                   |
| [../DATABASE.md](../DATABASE.md) y [DANGEROUS_MIGRATIONS.md](../DANGEROUS_MIGRATIONS.md) | E/R y riesgo de migración (no `DISTRIB/`, placeholder eliminado) |


> Si faltan filenames listados, busca el último título alineado en [../tecnica/](../tecnica/README.md): los nombres de archivo se renombran sin *romper* el repositorio.

---

## Convención de mantenimiento

- **Cambio de ruta o nombre de *feature***: actualizá *primero* `admin.php` / `web.php` y **después** el `.md` que dependa, o abrí un *issue* en el repositorio con *diff* de ruta.  
- **Cambio solo de *copy* o UI** (texto de un botón): *no* exige actualizar títulos, sí si cambia *condición* de un negocio.  
- **Añadir un módulo nuevo** en *admin* → crea un *subcapítulo* bajo `admin/`, pone un *anchor* al final de `00` y enlaza desde este *README*.

*Última reestructuración completa: Abril 2026.*