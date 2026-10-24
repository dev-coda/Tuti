# Guía: tienda pública y flujo de compra (cliente / tendero vía web)

Esta guía describe la experiencia **comercial**: buscar, armar el carrito, aplicar promociones y dejar un pedido. Complementa [04-carrito…](./04-carrito-checkout-y-ordenes.md) y [02-catálogo…](./02-catalogo-producto-y-buscador.md).

## Rutas y pantallas principales

| Ruta aprox. | Uso |
|-------------|-----|
| `/` | Inicio: categorías, banners, productos |
| `/busqueda/...` | Búsqueda y filtros |
| `/categoria-producto/...` | Categoría y listado |
| `/producto/{slug}` | Ficha (variaciones, precio, stock) |
| `/proveedores` y `/proveedores/{marca}` | Marcas |
| `/etiqueta-producto/{slug}` | Por etiqueta |
| `/carrito` | Carrito, cupones, **Entrega Standard / Especial**, fechas |
| `/formulario` | Alta / interesados B2B |
| `/cliente-nuevo` | Alta autoservicio / vendedor / sucursal (ver [03](./03-registro-alta-cuenta-tronex-y-sesion.md)) |
| `/ordenes` (autenticado) | Pedidos; **Mi Cuenta** con pestañas según rol |

## Contenido informativo

- Términos, privacidad, FAQ: rutas públicas de contenido.
- Páginas dinámicas `/contenido/{slug}`: [admin/11](../admin/11-contenido-banners-destacados-campanas-upsell.md).

## Flujo de compra típico

1. Navegar / buscar → ficha → “Lo quiero”.
2. Carrito: cantidades, cupón, método de entrega con fecha estimada.
3. Validaciones de inventario (y obsequios de bonificación) al confirmar.
4. Confirmación / gracias y correos según configuración.

Reordenar: desde detalle de pedido autenticado, sujeto a stock y precios vigentes.

## Autenticación

- `GET login` puede redirigir a `/formulario`.
- Contraseña, **código mágico** (6 dígitos) y reset: [03](./03-registro-alta-cuenta-tronex-y-sesion.md).
- Vendedor/supervisor: [roles/01](../roles/01-vendedor-rol-seller.md), [roles/03 Mi Cuenta](../roles/03-mi-cuenta-pestanas-y-visibilidad.md).

## Errores frecuentes

- Cantidad no válida: empaque / paso de venta.
- Stock / piso de seguridad: [04](./04-carrito-checkout-y-ordenes.md), [admin/05](../admin/05-inventario-bodegas-y-sincronizacion.md).
- Fecha de entrega: [05](./05-plazos-entrega-vista-comprador.md).

## Módulo tendero `/tendero`

Ver [roles/02](../roles/02-tendero-interfaz-shopper.md).

---

**Revisado:** septiembre 2026
