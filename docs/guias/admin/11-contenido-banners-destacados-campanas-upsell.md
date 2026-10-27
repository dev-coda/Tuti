# Contenido, marketing en sitio, plantillas y campañas

Agrupa **páginas fijas e informativas**, **contenido dinámico** por *slug*, **banners y destacados**, reglas de **compra asociada** (upsell), retención, **campañas** a nivel ajuste y, en referencia cruzada, **plantillas de email**. Las rutas admin están en [admin.php](../../routes/admin.php) y [web.php](../../routes/web.php) (páginas públicas).

> Detalle de implementación del editor: [VUE_RICH_TEXT_EDITOR](../VUE_RICH_TEXT_EDITOR_IMPLEMENTATION.md) y [CONTENT_PAGES_FEATURE](../CONTENT_PAGES_FEATURE.md) (técnico, inglés).

## Páginas legales e informativas (fijas en código)

- `/terminos-y-condiciones`, `/politicas-de-privacidad`, `/preguntas-frecuentes` (o equivalente según *ContentController* y vistas).
- Edición: según *admin*, puede mezclarse con **claves** de contenido estático; confirmar con el *menú* “Contenido / Legales”.

**Caso de uso** — *Actualizar política de devolución*: abrir clave o vista correspondiente en *admin* → *content*, editar texto, guardar, probar en incógnito en la ruta pública.

## Páginas con slug (CMS)

- Público: `GET` `/contenido/{slug}` → *showPage*.
- Admin: *resource* `content-pages` — listado, crear, editar, título, **slug** único, cuerpo (p. ej. editor rico) y *meta* (SEO) según formulario.
- `slug` se usa en enlaces; si se cambia, **actualizar** enlaces externos o redirecciones.

**Casos de uso:**

- Publicar *landing* de producto o campaña sin desplegar código: crear página, fijar slug, enlazarla desde *banner* o *banner lateral*.
- Cerrar temporalmente una oferta: desactivar o reemplazar contenido.

## Claves de contenido estáticas (settings-based)

- Prefijo `admin.content` — índice de **claves**; editar valor por *key* (T&C embebido, avisos globales, textos reutilizables en Blade).

## Banners (caruseles, laterales)

- Recurso `banners` — *Tipos* (p. ej. *banner principal* vs. *lateral*; el código distingue *type* numérico en *Banner*; ver *home* en *PageController* y modelo).
- Reordenar y activar; subir imágenes según *admin*.
- Vinculación a *URL* interna o a `/contenido/...` para *landing* de campañas.

## Destacados: productos y categorías

- `featured-products` (búsqueda, *toggle “más vendidos”*, título) — módulo de inicio/relacionados.
- `featured-categories` — *más populares*, personalización de *imagen*, etc.

**Caso** — *Destacar categoría gaseosas en Navidad:* subir imagen personalizada, título, orden y productos a mostrar (según UI).

## Upsell y reglas (cross-sell)

- `upsell-zones` y gestión de productos por *zona*; acciones: adjuntar, quitar, **ordenar** posiciones.
- `upsell-rules` — criterios de *cuándo* mostrar qué sugerencia (mínimos, categorías, etc. según modelo *Upsell*).

> Documentar lógica fina al implementar; aquí, **saber** dónde editar: *admin* → *Upsell* (nombres `admin.upsell-zones.*`, `admin.upsell-rules`).

## Retenciones (retenciones fiscales u otras)

- *Resource* `retentions` bajo nombres `admin.retentions.*` (parámetros legales / retención en *facturación* según negocio; revisar nombres de *fields* en formulario *admin*).

## Campañas (hub de ajustes comerciales)

- Prefijo `admin.campaigns` — `CampaignController`.
- **No** es un CRUD de “campañas” con ventana/mensajes sueltos: es un **panel** con estadísticas rápidas y **ajustes**:
  - Etiqueta automática **NUEVO** (`auto_tag_nuevo_enabled`)
  - Etiqueta automática **DESCUENTO** (`auto_tag_descuento_enabled`)
  - Usar productos más vendidos en destacados (`use_most_sold_products`)
  - Título de la sección de productos destacados (`featured_products_section_title`)
- Relacionado: módulos `featured-products` / `featured-categories` y tags del catálogo.

## Plantillas de email

- *CRUD* en `admin.email-templates.*` — cuerpos HTML, asuntos, *preview* y prueba (cruzado con *settings.mailer* y *test.email* de [configuracion.md](./configuracion.md)).
- Documentación de variables y convención de nombres: [../EMAIL_TEMPLATES.md](../EMAIL_TEMPLATES.md) (técnico, inglés).

**Uso** — *Cambio de template de “pedido recibido”:* editar plantilla → *preview* → *guardar*; enviar un pedido de *test* o usar la prueba de correo global con destinatario real.

## Relación con otras guías

- [b2b-tienda/01 — Visión general](../b2b-tienda/01-vision-general-rutas-y-flujos.md) — URLs públicas.
- [01 — Catálogo](./01-gestion-de-catalogo-producto-y-medios.md) — productos, etiquetas.
- [04 — Carrito (b2b)](../b2b-tienda/04-carrito-checkout-y-ordenes.md) — correos transaccionales de orden.

---

**Revisado:** septiembre 2026
