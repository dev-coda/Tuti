# Guía: tienda pública y flujo de compra (cliente / tendero vía web)

Esta guía describe la experiencia **comercial** de la plataforma: buscar, armar el carrito, aplicar promociones y dejar un pedido. Complementa [carrito-ordenes.md](./carrito-ordenes.md) y [catalogo-productos.md](./catalogo-productos.md).

## Rutas y pantallas principales

| Ruta aprox. | Uso |
|-------------|-----|
| `/` | Página de inicio: categorías, banners, productos recientes |
| `/busqueda/...` | Búsqueda y filtros (orden, categoría, marca según la URL) |
| `/categoria-producto/...` | Categoría y subcategorías, listado de productos |
| `/producto/{slug}` | Ficha de producto (variaciones, precio, “Lo quiero”, stock visible) |
| `/proveedores` y `/proveedores/{marca}` | Listado y detalle de marcas |
| `/etiqueta-producto/{slug}` | Listado por etiqueta |
| `/carrito` | Carrito de compras, cupones, resumen (no `/cart`) |
| `/formulario` | Registro de clientes; flujo de alta asociado al negocio |
| `/ordenes` y `/ordenes/{id}` (autenticado) | Listado y detalle de mis pedidos |
| `/ordenes/{id}/gracias` | Página de agradecimiento tras una compra (tras autenticar flujo) |

> Las rutas exactas pueden ampliarse: revisar [web.php](../../routes/web.php) si añadís nuevas páginas.

## Contenido informativo

- **Términos, privacidad, FAQ**: `/terminos-y-condiciones`, `/politicas-de-privacidad`, `/preguntas-frecuentes` (o equivalentes bajo [ContentController](../../app/Http/Controllers/Admin/ContentController.php)).
- **Páginas dinámicas** (`/contenido/{slug}`): contenidos administrados en el panel (véase [contenido-banners-campanas.md](./contenido-banners-campanas.md)).

## Flujo de compra típico (casos de uso)

### Caso 1: Navegar y comprar sin registro explícito previo (según reglas de negocio)

1. Elegir categoría o buscar.
2. Entrar a la ficha, elegir variación si aplica y cantidad (respetar **empaque** y **paso de venta** del producto).
3. Añadir con **“Lo quiero”**; el contador del carrito se actualiza.
4. Abrir el **carrito** (`/carrito`); ajustar cantidades o quitar líneas; opcional: **cupón** (véase [cupones.md](./cupones.md)).
5. Completar el pedido: el sistema aplica reglas de descuento, bonificaciones, impuestos y comprobará inventario/entregas. Si el registro es obligatorio, el flujo te llevará a **alta o inicio de sesión** (según la implementación actual; muchos B2B exigen formulario/validación previa).
6. Tras confirmar, se muestra agradecimiento o detalle de orden, y recibes comunicaciones por correo según la configuración.

### Caso 2: Reordenar un pedido anterior (usuario autenticado)

- En el detalle de un pedido existente, usar la acción de **reorden** (el backend expone reordenación de líneas) para llenar el carrito a partir de una orden previa, sujeto a stock y a precios/ promociones vigentes.

### Caso 3: Completar migración o perfil Tronex

- Algunos usuarios tienen un flujo dedicado: `/tronex/completar-perfil` (y pasos de migración `tronex` según ruta) para fijar correo y contraseña tras un alta migrada. Seguir indiciaciones en pantalla.

## Autenticación: enlaces frecuentes

- `login` puede redirigir al flujo de **formulario**; confirmar con la pantalla real de tu entorno.
- **Enlace mágico** o verificación: rutas bajo [auth.php](../../routes/auth.php) (magic link, `forgot-password`, `reset-password`).
- Completar verificación de correo si el sistema la exige antes de acceder a `/ordenes`.

## Cupones e impuestos

- Aplicar y quitar cupón se hace en el carrito: envíos POST a rutas `cart.coupon.*` (véase [cupones.md](./cupones.md)).
- Cálculo de **impuestos** y totales: descrito en [carrito-ordenes.md](./carrito-ordenes.md#cálculo-de-impuestos) y reglas de negocio de producto/marca.

## Errores frecuentes (usuarios)

- **“No deja en cantidad X”**: comprobar **múltiplo del empaque** y mínimo de venta; revisar ficha.
- **Sin stock o inventario reservado**: [inventario.md](./inventario.md).
- **Fecha de entrega inesperada**: [calendarios-entrega.md](./calendarios-entrega.md) y métodos de envío.

## Público: tendero (interfaz `/tendero`…)

Quien use el **módulo tendero** (prefijo de rutas bajo [Shopper\PageController](../../app/Http/Controllers/Shopper/PageController.php)) deberá leer además [tendero.md](./tendero.md) para inicio, productos, carrito, pedidos, contacto e informes.

---

**Revisado:** Abril 2026
