# Reportes, exportaciones, KPI y auditoría

Cubre el uso de **KPIs**, generación y descarga de **reportes**, **exportes mensuales** de pedidos, **ventas del día** y el **minidashboard** del vendedor, según [admin.php](../../routes/admin.php) y [OrderController](../../app/Http/Controllers/OrderController.php).

> Documentación adicional (inglés) en: [DAILY_SALES_REPORT.md](../DAILY_SALES_REPORT.md), [MONTHLY_EXPORTS_SYSTEM.md](../MONTHLY_EXPORTS_SYSTEM.md), [../PRODUCTION_DAILY_AUDIT.md](../PRODUCTION_DAILY_AUDIT.md).

## Dashboard y KPIs de administración

- **Ruta lógica:** `GET` bajo el prefijo `kpi` → `admin.kpi.*`
  - **Índice:** resumen de métricas; puede incluir enlace a **exportación** (`/kpi/export` → `admin.kpi.export`).
- **Casos de uso:**
  1. Abrir *KPI* o *Dashboard* desde el menú admin.
  2. Filtrar por rango o segmento (si la pantalla lo permite).
  3. **Exportar** a Excel/CSV cuando haya botón, para análisis en hoja de cálculo o BI.

> Si faltan controles concretos en vuestro build, nombres de ruta: `KpiController@index`, `KpiController@export`.

## Generador de reportes bajo demanda

- **Prefijo:** `reports` con nombre `admin.reports.*`
- **Listado e index:** ver reportes creados o disponibles.
- **Generar:** `POST` `admin.reports.generate` — pone en cola o genera un archivo.
- **Estado y descarga:** `admin.reports.status`, `admin.reports.download`.
- **Eliminación:** `admin.reports.destroy`.

**Caso de uso** — “Necesito un extracto de un tipo de dato (ventas, cupones, etc. según implementación)”:  
1) Elegir tipo o parámetros, 2) *Generar*, 3) Esperar *listo* (o refrescar estado), 4) *Descargar*.

> La lista exacta de *tipos* de reporte depende de [ReportController](../../app/Http/Controllers/Admin/ReportController.php).

## Ventas del día (daily sales)

- `GET` `admin.reports.daily-sales` — visualización o JSON según el controlador.
- `GET` `admin.reports.daily-sales.export` — **exportación** del corte de ventas diario.

**Uso típico:** cierre o seguimiento intradía, conciliación con caja/ERP, comparación con reportes técnicos de *audit* (véase sección *Auditoría*).

## Exportes de órdenes (incl. mensuales)

- `POST` `orders.export.monthly` — inicia (o encola) un **exporte mensual**; consultar notificación o listado.
- `GET` `admin.exports.list` — ver exportes en curso o terminados.
- `GET` `admin.exports.download` — bajar un fichero.
- `GET` `admin.exports.status` — comprobar *procesando* / *listo* / error.

**Casos de uso:**

- **Cierre de mes** contable: solicitar rango/periodo según el formulario, descargar cuando pase a *completado*.
- **Reproceso:** si un exporte falla, comprobar estado, revisar logs; repetir o escalar a soporte técnico (véase [MONTHLY_EXPORT_QUICK_START.md](../MONTHLY_EXPORT_QUICK_START.md) en repositorio).

Otras **exportaciones** desde listados: `userexport`, `sellerexport`, `productexport`, `orderexport` (nombres de ruta con prefijos `admin.export` según *admin*), `holidays-export`, `orderauditexport`, *contacts* `contactexport`.

## Reintentos e integración con ERP (relevante a reportes y órdenes)

No son “reportes” en el sentido contable, pero afectan la *calidad* de los datos: desde el detalle de un pedido se dispone (según `admin`):

- Reenviar pedido, reenviar emisiones XML, reintentar correo de **confirmación** o de **estado**.

Más en [carrito-ordenes.md](./carrito-ordenes.md) y [../ORDER_RETRY_QUICK_START.md](../ORDER_RETRY_QUICK_START.md).

## Mini-dashboard del vendedor (API)

- `GET` `/api/seller-dashboard` (usuario autenticado) — usado en integraciones o pantallas móviles. Ver [vendedor.md](./vendedor.md).

## Operaciones masivas (reporte de resultados)

- Módulo `bulk-operations` — tras una sincro de *clientes*, se puede **descargar** un *report* generado o **borrar** archivos viejos (`download-report`, `delete-report`).

**Caso de uso** — Sincro masiva: ejecutar, revisar el informe de líneas con error o conflictos, corregir en origen, repetir si aplica. Detalle de diseño: [BULK_OPERATIONS.md](../BULK_OPERATIONS.md).

## Auditoría diaria (técnico/operación)

- Los *runbooks* *Daily audit* / *Production daily audit* describen criterios técnicos de comprobación en producción; en **usuario fino** basta con revisar:
  1) Que los pedidos *Pending* tengan salida,  
  2) Uso de `orderauditexport` o procedimiento interno para cotejar **XML / ERP** si hay alertas.
- Documentación: [../PRODUCTION_DAILY_AUDIT.md](../PRODUCTION_DAILY_AUDIT.md) (en inglés).

## Errores y referencias

- Un exporte **stuck** en *processing*: revisar colas, logs y espacio en disco; ver [../tecnica/colas-y-horizon.md](../tecnica/colas-y-horizon.md).
- Cifras que no cuadran con contabilidad: alinear *timezone*, **fecha de corte** del *daily sales* y validar que no haya *órdenes canceladas* o en **espera** (para lo cual ver filtros y estados de orden en el listado *orders*).

---

**Revisado:** Abril 2026
