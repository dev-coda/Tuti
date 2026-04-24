# Guía: vendedor (rol `seller`)

Los usuarios con rol de **vendedor** atienden clientes y, en Tuti, pueden asociar la sesión a un **cliente concreto** para colocar pedidos en su nombre, ver su contexto de negocio y, según permisos, usar un mini-dashboard de actividad.

> Rutas relevantes en [admin.php](../../routes/admin.php) bajo `middleware` `auth` y `role:seller`.

## Asignar y quitar cliente activo

| Acción | Tipo | Nombre de ruta | Uso |
|--------|------|----------------|-----|
| Fijar cliente (sesión) | `POST` | `seller.setclient` | Cuerpo/parámetros según el formulario: identifica al cliente a “tomar” para el resto de la navegación. |
| Quitar cliente de la sesión | `POST` | `seller.removeclient` | Limpia la vinculación; el vuelve a ver el catálogo o flujo en modo neutral. |

**Caso de uso:** Un tendero o punto de venta reporta un pedido por teléfono. El vendedor entra con su usuario, asigna el `client` correcto, añade productos al carrito, aplica reglas (promos, mínimo de vendedor, ruta) y deja el pedido como si el cliente lo hubiera hecho él mismo, con trazabilidad bajo su usuario.

> Detalle de formularios y nombres de parámetros: buscar usos de `setclient` y `removeclient` en el frontend (vistas, Livewire, Vue) del proyecto.

## API de resumen (dashboard vendedor)

- `GET /api/seller-dashboard` — nombre de ruta `api.seller.dashboard` (bajo [web.php](../../routes/web.php) con `auth`).

Sirve para mostrar resumen (órdenes recientes, totales, etc. según implementación del [OrderController](../../app/Http/Controllers/OrderController.php)). Si la app web no lo muestra, puede consumir otras integraciones; documentar en UI cuando se expongan.

## Ruta pública *legacy* “/vendedor/…”

Rutas bajo comentario “DEPRECATED” en `web.php` para el antiguo panel de vendedor; **no** se deben confundir con el flujo actual. Cualquier enlace a documentos antiguos de “panel vendedor” deberá actualizarse al flujo con **rol** y asignación de **cliente**.

## Relación con otras guías

- [usuarios-autenticacion.md](./usuarios-autenticacion.md) — roles, permisos, sincronización de ruta.
- [carrito-ordenes.md](./carrito-ordenes.md) — pedidos, estados, integración con ERP.
- [descuentos-promociones.md](./descuentos-promociones.md) — mínimo de vendedor y reglas.
- [zonas-rutas.md](./zonas-rutas.md) — 48h y calendario por usuario/zona, cuando aplica al cliente bajo vendedor.

## Buenas prácticas

- Confirmar que el **cliente activo** es el correcto antes de cerrar el carrito; el impacto de precios, cupones e inventario depende del cliente.
- Cerrar sesión o `removeclient` al terminar para no mezclar pedidos entre comercios.

---

**Revisado:** Abril 2026
