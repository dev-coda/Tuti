# Tuti: introducción, negocio B2B, roles y terminología

Este documento sitúa al lector: **qué** es Tuti, **qué** tipo de usarios existen, y **cómo** se articulan el sitio público, el panel, la API y los sistemas externos. Es la base de lectura antes de abrir *carrito*, *admin* o *API*.

> Referencias técnicas de ruta: `Tuti/routes/web.php`, `Tuti/routes/admin.php`, `Tuti/routes/api.php` (Laravel; sin prefijo *admin* fijo: `admin.php` se carga con el *provider*; ver comentario en *RouteServiceProvider*).

## 1. Qué resuelve Tuti

Tuti es un **e‑commerce B2B** (venta a negocio, no a consumo final) orientado a:

- Un **catálogo** de producto complejo: variaciones, empaques, mínimos, inventario por **bodega** y, a veces, **combinaciones** o **padre/hijo** entre *SKUs*.
- Un modelo de **precio y descuento** en capas: proveedor, marca, vendedor, *promo* temporal, *cupón* con exclusiones, **bonificación** automática, **descuento por volumen**.
- **Cumplimiento** logístico: **método de entrega** (nombres del dominio, p. ej. *Tronex* vs. *Express*; ver `Order` y calendario), cálculo de **fecha** según *zona*, *ruta*, *ciclo* (A/B/C) y días *no laborable*.
- **Integración** hacia un ERP (Microsoft **Dynamics** por SOAP) para existencias, precio y, en el pedido, **transmisión XML** con reintento, cola y *jobs*.
- Comunicación por **email** (Mailgun + plantillas) y, según módulo, otras *apps* a través de **Laravel Sanctum**.

No es un “*marketplace*” *multivendedor* en el sentido C2C: el **vendedor** interno toma un **cliente** bajo su usuario y carga en su carrito, pero el *dueño* del catálogo y la lógica es un solo operador comercial.

## 2. Clases de actores (quién toca qué)

| Clase (Spatie/negocio) | Acceso | Objetivo típico |
|------------------------|--------|------------------|
| **Visitante / *guest*** | Sí, salvo *checkout* estricto | Navegación, a veces carrito, dependiendo de lógica de *middleware*; el **checkout y órdenes** en la práctica requieren **identidad** y contexto (zona, documento) |
| **Usuario cliente (negocio)** | Web autenticado | Hacer *pedido*, ver *historial*, reorden, cupones, dirección, zona. Usuario = fila en `users` (sin rol Spatie) |
| **Vendedor (`role:seller`)** | Web autenticado + acciones *seller* | Toma a un *cliente* bajo su sesión (`setclient` / `removeclient`), pasa por el carrito, ve órdenes donde figura; obtiene *dashboard* JSON (no *SPA* pública) |
| **Admin (`role:admin`)** | *Back-office* bajo *routes/admin.php* (mismo *namespace* de URL que otras, sin `/admin` obligatorio salvo *deploy*) | CRUD de *todo*; reportes, cupones, calendario, ajuste global, **operaciones masivas** |
| **Módulo “tendero” (*Shopper*)** | Ruta fija bajo `prefix('tendero')` | *UI* alternativa (en el código, controlador devuelve vistas; **poca o nula** lógica en el *controller*; no confundir con el *carrito* *principal* `/carrito` sin prefijo) |
| **Sistema o integración** | `auth:sanctum` o *token* | Clientes, productos, precios, inventario, *promociones*, *pedidos*; vía *REST*; **cierre de venta** sigue siendo **web o interno** según *integración* |

*Actor extra:* **migración Tronex** — *usuarios* que se traen con documento/telefono y luego exigen `tronex_migration_pending` = completar email y contraseña. Hay *middleware* que *bloquea* la mayoría de rutas hasta *completar*.

## 3. Términos frecuentes (glosario operacional)

- **Bodega:** centro de *stock*; el pedido baja *existencia* lógica según la **bodega** mapeada a la **zona** del *usuario*.
- **Ruta y ciclo (A/B/C):** partición *logística* en calendario; afecta **qué semana** puede entregarse, según *DeliveryCalendar* y *RouteCycle* (nombres de *model*/*admin*; ver *docs* 13 y 12 *admin*).
- **Rutero:** sincronía de *datos de ruta* del *cliente* hacia/ desde **SOAP** (por `UserRepository::syncUserRuteroData` y tareas *bulk*; ver *admin* 15).
- **Tronex / Express (y “48h / Coordinadora”):** nombres de *método* y *reglas* de **proveedor de entrega** y de **cálculo**; *Express* puede, según *zona*, *cotizarse* con *Coordinadora* vía *API* interna de la app, mientras otras *zonas* repli **can** 0. Ver `GET /api/shipping-quote/…` en *routes/api.php* y *Order::SHIPPING_PROVIDER_**`.
- **Estado PENDIENTE / EN ESPERA / *WAITING*:** pila *async*; “**En espera**” puede ser **diferir transmisión** a la **ruta* del *vendedor* (o *job* a futuro). *Detalles* en *04-carrito* y *ORDER_RETRY* en *docs técnica*.
- **XML (pedido):** cuerpo que *presales* hacia *Dynamics*; reintentos en *OrderRepository* / *ProcessOrderAsync*; pruebas en módulo *cupón-test*.
- **ERP / SOAP** no son visibles al tendero promedio; sí al **soporte** y a la **documentación técnica** (*integración*).

## 4. Cómo está “cortada” la URL pública (recordatorio)

* **Raíz e *home* *productos* (catálogo):** `GET /`, `search`, *categoría* jerárquica, `producto/{slug}`, *etiqueta de producto* (`label`), *marcas/proveedores* (`proveedores`).
* **Contenido legal/FAQ/estáticos+slug:** *ContentController*; **no** uses *legacy* `GET /terms` si aún *apuntara* a un *method* faltante en *PageController*; los legales actuales son los de `content.terms` / *privacy* / *faq* y, si hiciera falta, `contenido/{slug}`.
* **Carrito *principal* (sesión, precios, cupones, retenciones, vendedor):** *prefijo* `/carrito` y *patch* a `/cart/update` (cuidado: nombres mezclados, ver *web*).
* **Cuenta / *login*:** *GET* `login` *redirige* al **formulario** B2B, no a un *login* clásico. El inicio *POST* a sesión pasa por *Breeze* `AuthenticatedSessionController@store` en `auth` *routes*.
* ***API* pública/ mixta (sin *Sanctum*):** categoría raíz, *cart* *JSON* desde *sesión*, *vacation*, *shipping-quote*, *cities*… ver *README técnica API*.

*Esta sección* **intencional** no reemplaza el listado 1:1: `01-vision` y *web* son la *fuente*.

## 5. Cómo leer el resto de *guias* en esta carpeta

| Situación | Documento de entrada sugerido |
|----------|---------------------------------|
| “*Sólo* quiero poner un pedido” | `04-carrito` + `01-vision` |
| *Calendario y “por qué mi entrega* es *martes* *”* | *Cliente:* `05-plazos-entrega-vista-comprador` *→* *Operador* `13-calendario` (admin) |
| *Cómo se crea* un *cupón* o *bajo qué* se aplica* | *Admin* `09-cupones` y `08-bonos` (y `07` si hay *macro-reglas* de *volumen* / *promo*) |
| *Cómo se sincroni**za** * mi *cliente* *antes* del *pedido* * | *Admin* `15` (bulk) + nota *automática* en *CartController* (doc *bulk* en *inglés* sigue; el *núcleo* está *aquí* en 15) |

## 6. Cumplimiento y riesgo

- **Cambio de lógica de *precio* o *impuestos*:** afecta **cálculo* de *cupones*, *XML* e *invoicing*; nunca tocar *prod* *sin* *QA* y *muestras* de *pedido de prueba*.
- ***Horizon* o *DB queue* *caída* *:** *pedido* creado pero *async* retraso — ver *técnica* *colas* y *monitor* de *failed_jobs*.
- ***Datos personale*s *:** Tuti guarda *documento*, *teléfono* y *relación* con *negocio*; la *política* de *tratamiento* y *T&C* *están* bajo *ContentController*; el *DPO* del *operador* debe *etiquetar* *cada* *uso* (este *repositorio* *no* es *jurídico*).

---

*Capítulo* **00** (suplemento al *repositorio*; no sustituye *código* ni *contrato* *legal*). *Revisión: abril 2026.*
