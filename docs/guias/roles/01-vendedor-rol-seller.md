# Guía: vendedor (rol `seller`) y supervisor

Los usuarios con rol **vendedor** atienden clientes y pueden asociar la sesión a un **cliente concreto** para colocar pedidos en su nombre. Los **supervisores** cubren zonas/rutas asignadas y operan principalmente desde **Mi Cuenta** (no el panel admin completo).

> Rutas de sesión vendedor en [web.php](../../routes/web.php) / middleware `auth` + rol. Detalle de pestañas: [03 — Mi Cuenta](./03-mi-cuenta-pestanas-y-visibilidad.md).

## Asignar y quitar cliente activo

| Acción | Tipo | Nombre de ruta | Uso |
|--------|------|----------------|-----|
| Fijar cliente (sesión) | `POST` | `seller.setclient` | Identifica al cliente a “tomar” para el resto de la navegación. |
| Quitar cliente de la sesión | `POST` | `seller.removeclient` | Limpia la vinculación. |

**Caso de uso:** Un tendero reporta un pedido por teléfono. El vendedor entra, asigna el cliente, arma el carrito y deja el pedido con trazabilidad bajo su usuario (**origen RUTA**).

## Mi Cuenta (operación diaria)

- **Pedidos del día** — listado filtrable del día (zona amplia para seller/supervisor cuando aplica cobertura).
- **Mi Ruta** (solo seller) — clientes de la ruta/día, búsqueda, agregar sucursal.
- **Mis Zonas** (solo supervisor) — asignaciones ruta/zona y pedidos de esa cobertura.
- **Sin pestaña Direcciones** para seller/supervisor.
- Badges **RUTA** / **Autónomo** en listados de pedidos.

## API de resumen (dashboard vendedor)

- `GET /api/seller-dashboard` — nombre `api.seller.dashboard` (auth).

Resumen de órdenes/totales según `OrderController@sellerDashboard`.

## Ruta pública *legacy* “/vendedor/…”

Rutas bajo comentario DEPRECATED en `web.php`; **no** usar frente al flujo actual por rol + cliente activo.

## Relación con otras guías

- [03 — Mi Cuenta: pestañas](./03-mi-cuenta-pestanas-y-visibilidad.md)
- [10 — Usuarios y vendedores (admin)](../admin/10-usuarios-vendedores-y-accesos.md)
- [04 — Carrito y órdenes](../b2b-tienda/04-carrito-checkout-y-ordenes.md)
- [07 — Descuentos](../admin/07-descuentos-y-promociones-englobado.md)
- [12 — Zonas y 48h](../admin/12-zonas-rutas-y-48-horas.md)

## Buenas prácticas

- Confirmar el **cliente activo** antes de cerrar el carrito.
- Cerrar sesión o `removeclient` al terminar para no mezclar comercios.
- Interpretar origen **RUTA** vs **Autónomo** al revisar desempeño del día.

---

**Revisado:** septiembre 2026
