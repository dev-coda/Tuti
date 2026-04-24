# API HTTP: referencia (Tuti)

**Base URL:** `APP_URL` + prefijo `/api` (ver `Tuti/routes/api.php` y `RouteServiceProvider`).

**Middleware de grupo** `api` (p. ej. throttling) salvo anotación explícita. Rutas bajo `auth:sanctum` requieren token *Bearer* Sanctum o cookie de sesión móvil según cliente.

> Documentación de **paginación y parámetros** en [API_FILTERING_PAGINATION.md](../API_FILTERING_PAGINATION.md) y [API_QUICK_REFERENCE.md](../API_QUICK_REFERENCE.md) (inglés).

---

## 1. Autenticación y usuario

| Método | Ruta | Controlador / acción | Middleware adicional |
|--------|------|------------------------|------------------------|
| GET | `/api/user` | Devuelve usuario autenticado | `auth:sanctum` |

---

## 2. Público (sin `auth:sanctum` en el grupo *sanctum*)

Definido dentro de `Route::middleware('api')->group(...)` a menos que otra *closure* añada Sanctum.

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/categories` | Categorías raíz activas con hijos (closure). |
| GET | `/api/cart` | Carrito en **sesión**; mismas cookies de sitio. Devuelve `items` y `totales` simplificados. |
| GET | `/api/cities` | `CityController@index` — listado (filtro p. ej. `state` para registro). |
| GET | `/api/products/latest` | `ProductsApiController@latest` — vitrina. |
| GET | `/api/products/most-sold` | Más vendidos. |
| GET | `/api/products/section-title` | Título configurable *hero* de productos. |
| GET | `/api/categories/featured` | Categorías destacadas / más populares según *settings*. |
| GET | `/api/categories/most-popular` | Populares por *joins* de *order_products* (hasta 4, según lógica). |
| GET | `/api/categories/section-title` | Título de *sección* *featured*. |
| GET | `/api/vacation-mode` | `Setting::getVacationModeInfo` — *enabled*, *active* en rango, fechas, *message*. |
| GET | `/api/delivery-date/{method}` | Fecha de entrega según *método* y *zona* (session o `?zone_id=`) vía `OrderRepository::getDeliveryDateByMethod`; texto en español + `raw_date`. |
| GET | `/api/shipping-quote/{method}` | Cota de envío *express*; puede devolver proveedor *Tronex* con coste 0 o *Coordinadora* con *shipping_cost*; valida *zona*; error 422 si *zona* inexistente o fallo de cotización. |

**Solo en entornos `!app()->isProduction()`:**  
- `POST /api/internal/fv-mock` — *mock* de facturación/fulfillment; protección por cabecera *token* de configuración.

**Clase `CartApiController`:** implementación alternativa del *cart*; **comprobar** si hoy está enlazada: la ruta activa es *closure* en *api.php*.

---

## 3. Bajo `auth:sanctum` (grupo con prefijos)

Todas usan el prefijo al **relativo a `/api`**; por ejemplo: `GET /api/clientes/`.

| Prefijo / rutas | Controlador | Uso resumido |
|-----------------|------------|-------------|
| `clientes` | `ClientesApiController` | `index` (lista, filtros, `ApiPaginationTrait` típica), `show` por `{client}` = `User`. |
| `productos` | `ProductosApiController` | `index`, `show` con relaciones; filtros; opcional bodega en query. |
| `precios` | `PreciosApiController` | *Catálogo* *precio*; `show` con `final_price` y variaciones. |
| `promociones` | `PromocionesApiController` | Lista cupones; `show` por *coupon*; `POST validar` con `cart_total` opcional. |
| `inventarios` | `InventariosApiController` | `index`, `show` por *producto*, `byBodega` con código. |
| `pedidos` | `PedidosApiController` | `index`, `show` por *order*, `byCustomer` con `{customer}` = `User`. |

Parámetros de *paginación*, *orden* y *filtro* replican los patrones documentados en los archivos *API_* en la raíz de `docs/`.

---

## 4. Códigos de error habituales

- **401 / 403** en rutas *sanctum*: *token* ausente, revocado o *scope* inadecuado.  
- **422** en *shipping-quote* o *delivery-date*: *zona* faltante o lógica de *Express*/Coordinadora *no* aplicable.  
- **429** (throttle del grupo *api*): 1000 *req*/min por *IP* (ajustar en *Kernel* si aplica *deploy*).

---

## 5. Integración y clientes móviles/externos

- Los consumidores **puros API** (sin cookie de *Blade*) deben obtener *token* Sanctum (flujo *SPA* o *device*) según *config* del proyecto, **no** descrito de nuevo aquí — ver `Laravel\Sanctum` y *routes* en *auth*.

---

*Fecha alineada con `api.php` del repositorio — abril 2026. Revalidar con `php artisan route:list` tras cada *merge* con cambios de rutas.*
