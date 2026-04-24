# Guía: módulo “Tendero”

Vista y funcionalidades bajo el prefijo de ruta **/tendero** (nombres de ruta con prefijo `shoppers.`), para el perfil de **tendero** o canal equivalente. Controlador: [Shopper\PageController](../../app/Http/Controllers/Shopper/PageController.php).

## Páginas disponibles (según controlador)

| Ruta lógica | Nombre (helper) | Propósito |
|-------------|-----------------|-----------|
| `GET /tendero` | `shoppers.home` | Página de inicio del módulo tendero |
| `GET /tendero/products` | `shoppers.products` | Listado o vitrina de productos (vista `shopper.pages.products`) |
| `GET /tendero/cart` | `shoppers.cart` | Carrito de este flujo (separado del de la tienda principal) |
| `GET /tendero/orders` y `/tendero/orders/{id}` | `shoppers.orders`, `shoppers.order` | Listado y detalle de pedidos en este contexto |
| `GET /tendero/contact` | `shoppers.contact` | Contacto |
| `GET /tendero/reports` | `shoppers.reports` | Informes propios del tendero |

> Si el diseño de negocio desvía a los tenderos a la **tienda principal** (`/`, `/carrito`), alinear con operaciones; la documentación de **compra y carrito** común sigue en [guia-tienda-y-compra.md](./guia-tienda-y-compra.md) y [carrito-ordenes.md](./carrito-ordenes.md).

## Casos de uso

1. **Consultar catálogo dedicado**  
   Entrar a `/tendero/products` y añadir al carrito con los controles de la plantilla. Verificar coherencia de **precio final** (descuentos, bonos, vendedor asignado) con [descuentos-promociones.md](./descuentos-promociones.md).

2. **Gestionar el carrito en ruta /tendero/cart**  
   Similar al carrito global pero en plantilla *shopper*. Actualizar cantidades, vaciar, finalizar el pedido según botones y validaciones de la UI.

3. **Seguimiento de pedidos**  
   Revisar histórico en “órdenes”; el detalle muestra el estado (coherente con el ERP / estados de sistema descritos en [carrito-ordenes.md](./carrito-ordenes.md)).

4. **Informes**  
   Uso de `/tendero/reports` según el contenido de la vista `shopper.pages.reports` (KPI, exporte simple o enlace; si la vista se amplía, ampliar esta sección en la misma guía).

5. **Contacto**  
   Enviar solicitudes o dudas por la pantalla de contacto; las gestiones al backend pueden vincularse a [contenido-banners-campanas.md](./contenido-banners-campanas.md) o al flujo de contactos internos (admin: `contacts`).

## Diferencia con vendedor y con cliente B2B general

- **Vendedor (rol `seller`)**: asigna cliente, ve dashboard de vendedor; [vendedor.md](./vendedor.md).
- **Tendero**: enfoque en un **subcanal** de la web (menú, informes, carrito bajo /tendero) sin reemplazar toda la tienda.

## Problemas habituales

- **Cambios en carrito que no coinciden con /carrito**  
  Son flujos distintos: confirmar con qué URL se está trabajando.
- **Precios o disponibilidad distintos a la ficha pública**  
  Revisar asignación de vendedor, zona, calendario de entrega e inventario.

---

**Revisado:** Abril 2026
