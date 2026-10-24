# Mi Cuenta: pestañas según rol

Pantalla **Mi Cuenta** (`clients.orders.index`, URL habitual `/mis-pedidos` o equivalente del menú). Controlador: `OrderController@index`.

Solo se **carga el contenido de la pestaña activa** (evita timeouts en supervisores con muchas zonas).

## Matriz de pestañas

| Pestaña (UI) | Clave `tab` | Cliente / tendero | Vendedor (`seller`) | Supervisor |
|--------------|-------------|-------------------|---------------------|------------|
| Pedidos / historial | `orders` | Sí | Sí | Sí |
| Pedidos del día | `orders-today` | No | Sí (por defecto) | Sí |
| Mi Ruta | `mi-ruta` | **No** (oculto) | Sí | No (usa Mis Zonas) |
| Mis Zonas | `mis-rutas` | No | No | Sí (por defecto; p. ej. tras magic link) |
| Cuenta | `account` | Sí | Sí | Sí |
| Direcciones | `addresses` | Sí | **No** (oculto) | **No** (oculto) |

## Origen del pedido (badges)

En listados de Mi Cuenta (y admin) las órdenes pueden mostrar origen:

- **RUTA** — pedido creado por un vendedor/supervisor en nombre del cliente (`seller_id` presente).
- **Autónomo** — el cliente lo colocó por sí mismo.

Componente: `<x-order-origin>` (también puede indicar cliente nuevo/actual según datos).

## Mi Ruta (vendedor)

- Lista clientes de la **zona** del vendedor para la ruta seleccionada y el día de visita de hoy.
- Filtros: selector de ruta, búsqueda (`ruta_q`), orden de secuencia de visita.
- **Agregar sucursal:** enlace a `/cliente-nuevo?mode=sucursal&return=mi-ruta` (alta ligada al flujo de cliente nuevo).

## Mis Zonas (supervisor)

- Panel de asignaciones ruta/zona del supervisor (`supervisorRoutes`).
- Al elegir una asignación, lista pedidos de esa cobertura (hoy / filtros).
- Los supervisores **no** usan el menú admin completo: operan desde Mi Cuenta.

## Direcciones

Solo clientes. Vendedor y supervisor no ven ni editan direcciones desde esta pantalla.

## Relación con otras guías

- [01 — Vendedor](./01-vendedor-rol-seller.md)
- [03 — Registro y sesión](../b2b-tienda/03-registro-alta-cuenta-tronex-y-sesion.md) (magic link → supervisor a `tab=mis-rutas`)
- [04 — Carrito y órdenes](../b2b-tienda/04-carrito-checkout-y-ordenes.md)

---

**Revisado:** septiembre 2026
