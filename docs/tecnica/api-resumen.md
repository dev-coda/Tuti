# API REST: uso general

Resumen en español. La referencia de parámetros, paginación y filtrado está en **[API_QUICK_REFERENCE.md](../API_QUICK_REFERENCE.md)** (inglés).

## Autenticación

- Muchos endpoints orientados a integración usan middleware adecuado; donde aplique, **Laravel Sanctum** u otro mecanismo documentado en las rutas de [api.php](../../routes/api.php).

## Patrones comunes

| Necesidad | Parámetros típicos |
|-----------|--------------------|
| Paginación con páginas | `per_page`, `page` |
| Límite sin “página” | `limit`, `offset` |
| Orden | `sort_by` / `order_by` + `sort_direction` / `order` (`asc` o `desc`) |
| Búsqueda de texto | `search` |
| Filtros exactos | p. ej. `category_id`, `status_id`, `zone` |
| Rangos | `min_price`, `max_price`, `date_from`, `date_to`, etc. |
| Varios IDs | `product_ids=1,2,3` o listas según el endpoint |

### Ejemplos (patrones)

```http
GET /api/clientes?per_page=25&page=2
GET /api/productos?limit=50&offset=0&sort_by=name&sort_direction=asc
GET /api/pedidos?date_from=2025-01-01&date_to=2025-12-31
```

Endpoints concretos (categorías públicas, carrito en JSON, clientes, productos, precios, inventarios, pedidos, promociones, etc.) están registrados en `routes/api.php` y comentados en [api-documentation.md](../api-documentation.md).

## Buenas prácticas

- Especificar siempre versión/entorno de destino (stage / producción).
- Respetar *rate limits* y políticas CORS del servidor.
- Revisar respuestas de error JSON y códigos HTTP.
- Para cambios de contrato, coordinar con el backend y documentar en pull request.

---

**Revisado:** Abril 2026
