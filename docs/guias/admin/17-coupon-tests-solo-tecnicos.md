# Módulo de pruebas de cupones (solo técnicos y operaciones avanzadas)

Rutas de administración bajo el prefijo con nombre de ruta `coupon-tests` → controlador `CouponTestController` (métodos: *index*, previsualizar XML, formulario *mock*, *suite* de escenarios, progreso, resultados, export, etc.; ver `Tuti/routes/admin.php`).

## Qué resuelve

- Generar o inspeccionar el **XML** (o payload equivalente) de un pedido de prueba, para *emparejar* la lógica de `CouponDiscountService` con lo que *Dynamics* / *SOAP* espera.
- **No** reemplaza pruebas E2E en *staging* con datos reales, ni reemplaza el ajuste de negocio de cupones en [09-cupones-gestion-avanzada.md](09-cupones-gestion-avanzada.md). Es una **herramienta de simulación y regresión técnica** antes o después de *hotfix* de precio empaquetado, línea de *bono*, o mapeo de *variation item* a XML.

## Párrafos de uso (a alto nivel)

1. **Vista de índice** — enlace a flujos de *mock*, *suite*, exportaciones *CSV* o JSON de *resultado* (según versión del *controller*).
2. **Vista o acción *preview* XML** — vuelca el cuerpo que *OrderRepository* / servicios de diagnóstico construirían, útil con `grep` o *diff* junto a los logs reales de `orders.response`.
3. **Formulario *mock*** — *POST* que arma un carrito o pedido sintético, ejecuta la lógica de *cupon* bajo *escenario*; validar en código si toca o no la cola hacia *ERP* (nunca *asumir* *write* a producción de *Dynamics*).
4. **Suite de escenarios** — *batería* de *casos*; puede tardar: monitorizar *Horizon* y no reiniciar *worker* a mitad de batería.
5. **Progreso / resultados** — *polling*; export final para *adjuntar* a *Jira* o *Confluence*.

## Seguridad y datos

- En **producción** el módulo solo debe ser accesible a personal autorizado. Si vuestro despliegue añade *IP allowlist* o *feature flag*, el manual interno *no* reemplaza esa *política* de *infraestructura*.
- No *mock* *con* emails *reales* de terceros sin **anonimizar**; usar `@example.com` o sandbox de *stage*.

## Documentos relacionados (inglés)

- [../../coupon-suite-runbook.md](../../coupon-suite-runbook.md) — *runbook* de la batería automatizada.  
- [../../tests/README-CouponTests.md](../../../tests/README-CouponTests.md) — *tests* Pest.  
- [09](09-cupones-gestion-avanzada.md) — CRUD y reglas *de* *negocio* reales.  
- [tecnica](../../tecnica/README.md) — *reintentos* de XML y *ProcessOrder*.

## Relación con módulo “9 – Cupones”

| Módulo 9 | Módulo 17 |
|----------|-----------|
| Crea, edita y activa **cupones** que impactan a clientes reales. | Simula o **diagnostica** la traducción a *XML* / baterías de *caso*. |
| Ajuste de mínimos, zonas, *mass create*. | *No* hace *mass create*; puede importar códigos solo como *dato* *de* *prueba*. |

Cualquier cambio en la regla de *precio* cupón requiere **tanto** *QA* *funcional* *en* *9* **como** (si aplica) *re-ejecución* de *suite* en 17 o en la CI.

---

*Revisión: abril 2026.*
