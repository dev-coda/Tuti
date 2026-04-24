# Manuales de usuario — plataforma Tuti

Bienvenido a la documentación de **uso** de Tuti, redactada en **español** y organizada por **ámbito de trabajo** (catálogo, operación comercial, administración, vendedor, etc.).

El **índice de toda la carpeta** `docs` (técnica + usuarios) está en [../README.md](../README.md).

## Por rol o audiencia

| Perfil | Guías recomendadas (orden sugerido) |
|--------|-------------------------------------|
| **Cliente o tendero (web principal)** | [guia-tienda-y-compra](./guia-tienda-y-compra.md) → [catalogo-productos](./catalogo-productos.md) → [carrito-ordenes](./carrito-ordenes.md) |
| **Usuario del módulo /tendero** | [tendero](./tendero.md) (y, si aplica, [guia-tienda-y-compra](./guia-tienda-y-compra.md)) |
| **Vendedor (rol *seller*)** | [vendedor](./vendedor.md) → [usuarios-autenticacion](./usuarios-autenticacion.md) → [descuentos-promociones](./descuentos-promociones.md) |
| **Administrador (panel *admin*)** | [admin-panel-y-modulos](./admin-panel-y-modulos.md) → módulos de abajo según tarea diaria |
| **Marketing o contenido** | [contenido-banners-campanas](./contenido-banners-campanas.md) ↓ (si editáis ofertas) [bonificaciones](./bonificaciones.md) / [cupones](./cupones.md) |
| **Operaciones, logística, informes** | [reportes-y-exportaciones](./reportes-y-exportaciones.md) → [calendarios-entrega](./calendarios-entrega.md) → [zonas-rutas](./zonas-rutas.md) |
| **Finanzas o conciliación** | [descuentos-promociones](./descuentos-promociones.md) → [reportes-y-exportaciones](./reportes-y-exportaciones.md) → (si aplica) [impuestos / envío] en *admin* — ver [configuracion](./configuracion.md) |

## Índice alfabético y por dominio

### Núcleo comercial y flujo

- [guia-tienda-y-compra](./guia-tienda-y-compra.md) — Rutas públicas, búsqueda, carrito, registro, órdenes y casos frecuentes
- [catalogo-productos](./catalogo-productos.md) — Productos, categorías, variaciones, etiquetas, empaque
- [carrito-ordenes](./carrito-ordenes.md) — Carrito, creación de pedido, estados, integración ERP, impuestos
- [tendero](./tendero.md) — Interfaz bajo `/tendero`
- [vendedor](./vendedor.md) — Asignar cliente, dashboard API

### Precio, promoción e inventario

- [descuentos-promociones](./descuentos-promociones.md)
- [bonificaciones](./bonificaciones.md)
- [cupones](./cupones.md)
- [inventario](./inventario.md)

### Plazos, geographicos e integración de entrega

- [zonas-rutas](./zonas-rutas.md)
- [calendarios-entrega](./calendarios-entrega.md)
- [usuarios-autenticacion](./usuarios-autenticacion.md) (incluye roles, 48h por zona, etc. cuando aplique)
- [configuracion](./configuracion.md) (modo vacaciones, ajuste global, correo, inventario, express 48h, etc.)

### Administración, contenido e informes

- [admin-panel-y-modulos](./admin-panel-y-modulos.md) — Mapa de recursos del back-office
- [reportes-y-exportaciones](./reportes-y-exportaciones.md) — KPI, reportes, exportes de órdenes, *daily sales*
- [contenido-banners-campanas](./contenido-banners-campanas.md) — Páginas, banners, destacados, upsell, retención, campañas, email

### Complemento histórico

- [manual-cupones-descuentos (consolidado legado)](../manual-cupones-descuentos.md) — Situarse junto a [cupones](./cupones.md) y [descuentos-promociones](./descuentos-promociones.md)
- [manual-inventario-por-zonas (consolidado)](../manual-inventario-por-zonas.md) — Cruzar con [inventario](./inventario.md) y [zonas-rutas](./zonas-rutas.md)

## Documentación de *historias* y requisitos

En [../historias-usuario/](../historias-usuario/) hay *user stories* en Markdown; deberían coincidir con el comportamiento descrito en estos manuales. Si no, abrir tarea o actualizar el manual.

## Convenciones de estilo (lectura)

- **Negrita** — conceptos clave o títulos de menú
- *Cursiva* — matices o excepciones
- `código` — nombres de ruta, campos o claves
- Avisos: **Nota**, **Advertencia**, **Consejo** según se use en el documento

## Última revisión estructural

Abril 2026. Las fechas puntuales dentro de capítulos *legacy* (p. ej. “Diciembre 2025” en secciones antiguas) deberán integrarse poco a poco; priorizar el texto de secciones con pie **Revisado: 2026**.

## Soporte

Para dudas de *implementación* o despliegue, consulte [../tecnica/README.md](../tecnica/README.md). Para requisitos de *negocio*, coordinar con producto/operación.
