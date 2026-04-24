# Documentación técnica e índice

Guías operativas en español y mapa del resto de material técnico del repositorio (`/docs`).

## Guías principales (español)

| Documento | Tema |
|-----------|------|
| [Despliegue a producción](./despliegue.md) | Script `deploy.sh`, modos de despliegue, pasos manuales, incidencias comunes |
| [Colas, trabajos en background y Horizon](./colas-y-horizon.md) | Workers, Redis, sincronización de inventario, Horizon |
| [Resumen de API (paginación y filtros)](./api-resumen.md) | Uso básico de *query*; complemento de la referencia de rutas |
| [**Referencia de rutas HTTP (API)**](./api-referencia-completa.md) | Todos los endpoints con método y controlador; abril 2026 |
| [Integración ERP, SOAP, cola, Mailgun, Coordinadora](./integracion-erp-soap-cola-y-correo.md) | Mapa de *jobs* y *servicios* hacia *Dynamics* y *envío* |

## Base de datos y modelo de datos

| Documento | Tema |
|-----------|------|
| [DATABASE.md](../DATABASE.md) | Entidades, tablas y relaciones (revisar junto a migraciones) |

## API REST

| Documento | Tema | Idioma |
|-----------|------|--------|
| [API_QUICK_REFERENCE.md](../API_QUICK_REFERENCE.md) | Parámetros, paginación, ordenamiento, filtros | EN |
| [API_FILTERING_PAGINATION.md](../API_FILTERING_PAGINATION.md) | Detalle de filtrado y paginación | EN |
| [api-documentation.md](../api-documentation.md) | Documentación general de API | EN |
| [API_ENHANCEMENTS_SUMMARY.md](../API_ENHANCEMENTS_SUMMARY.md) | Resumen de mejoras | EN |

## Correo y notificaciones

| Documento | Tema | Idioma |
|-----------|------|--------|
| [MAILGUN.md](../MAILGUN.md) | Configuración Mailgun | EN |
| [EMAIL_TEMPLATES.md](../EMAIL_TEMPLATES.md) | Plantillas de correo en la app | EN |
| [EMAIL_TROUBLESHOOTING.md](../EMAIL_TROUBLESHOOTING.md) | Diagnóstico de problemas de envío | EN |

## Colas, trabajos y despliegue (referencias en inglés)

| Documento | Tema |
|-----------|------|
| [../README-QUEUE.md](../README-QUEUE.md) | Colas para sync de inventario, Supervisor |
| [../QUEUE-SETUP-REQUIRED.md](../QUEUE-SETUP-REQUIRED.md) | Requisitos de cola |
| [../HORIZON-SETUP.md](../HORIZON-SETUP.md) | Configuración Horizon (stage/producción) |
| [../DEPLOYMENT.md](../DEPLOYMENT.md) | Duplicado del contenido traducido en [despliegue.md](./despliegue.md) |

## Órdenes, reintentos y reportes

| Documento | Tema | Idioma |
|-----------|------|--------|
| [ORDER_RETRY_SYSTEM.md](../ORDER_RETRY_SYSTEM.md) | Sistema de reintento hacia ERP | EN |
| [ORDER_RETRY_QUICK_START.md](../ORDER_RETRY_QUICK_START.md) | Inicio rápido reintentos | EN |
| [DAILY_SALES_REPORT.md](../DAILY_SALES_REPORT.md) | Reporte diario de ventas | EN |
| [MONTHLY_EXPORTS_SYSTEM.md](../MONTHLY_EXPORTS_SYSTEM.md) | Exportes mensuales de órdenes | EN |
| [MONTHLY_EXPORT_QUICK_START.md](../MONTHLY_EXPORT_QUICK_START.md) | Inicio rápido exportes | EN |
| [PRODUCTION_DAILY_AUDIT.md](../PRODUCTION_DAILY_AUDIT.md) | Auditoría diaria en producción | EN |

## Contenido, editores y front

| Documento | Tema | Idioma |
|-----------|------|--------|
| [CONTENT_PAGES_FEATURE.md](../CONTENT_PAGES_FEATURE.md) | Páginas de contenido dinámicas | EN |
| [VUE_RICH_TEXT_EDITOR_IMPLEMENTATION.md](../VUE_RICH_TEXT_EDITOR_IMPLEMENTATION.md) | Editor rico en Vue | EN |
| [EDITORJS_IMPLEMENTATION.md](../EDITORJS_IMPLEMENTATION.md) | Editor.js | EN |

## Reglas de negocio (referencia)

| Documento | Tema | Idioma |
|-----------|------|--------|
| [BONIFICATIONS.md](../BONIFICATIONS.md) | Bonificaciones | EN |
| [VENDOR_DISCOUNTS.md](../VENDOR_DISCOUNTS.md) | Descuentos por vendedor | EN |
| [HOLIDAYS_SYSTEM.md](../HOLIDAYS_SYSTEM.md) | Sistema de festivos | EN |
| [BULK_OPERATIONS.md](../BULK_OPERATIONS.md) | Operaciones masivas (sync clientes) | EN |
| [GLOBAL_INVENTORY_MINIMUM.md](../GLOBAL_INVENTORY_MINIMUM.md) | Mínimo global de inventario | EN |
| [ORDERABLE_STOCK_DISPLAY.md](../ORDERABLE_STOCK_DISPLAY.md) | Cómo se muestra el stock | EN |
| [EMERGENCY_ORDER_PROCESSING.md](../EMERGENCY_ORDER_PROCESSING.md) | Procesamiento de emergencia de pedidos | EN |

## Calidad, pruebas y runbooks

| Documento | Tema | Idioma |
|-----------|------|--------|
| [../coupon-suite-runbook.md](../coupon-suite-runbook.md) | Suite de pruebas de cupones | EN |
| [../coordinadora-48h-stage-checklist.md](../coordinadora-48h-stage-checklist.md) | Checklist 48h Coordinadora (stage) | EN |
| [../tests/README-CouponTests.md](../../tests/README-CouponTests.md) | Pruebas automatizadas de cupones | EN |

## Migraciones y riesgo

| Documento | Tema | Idioma |
|-----------|------|--------|
| [DANGEROUS_MIGRATIONS.md](../DANGEROUS_MIGRATIONS.md) | Migraciones de alto riesgo | EN |
| [../MANUAL_POSTGRESQL_FIX.md](../MANUAL_POSTGRESQL_FIX.md) | Ajuste manual en PostgreSQL (legado) | EN |

## Diagramas

| Documento | Tema |
|-----------|------|
| [../diagrams/README.md](../diagrams/README.md) | Instrucciones y diagramas Mermaid |

## Fixes puntuales (`docs/fixes/`)

Documentación de incidentes y parches. Consultar [../fixes/](../fixes/) para el listado. Sirven como historial, no sustituyen las guías de módulo.

## Historias de usuario (`docs/historias-usuario/`)

Requisitos o historias en Markdown; alinear con [manuales de usuario](../manuales-usuario/) cuando apliquen a capacidades reales en producción.

---

**Última actualización del índice:** Abril 2026
