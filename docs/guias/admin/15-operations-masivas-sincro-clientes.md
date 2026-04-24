# Operaciones masivas: sincronización de clientes (Rutero) e informes

**Dónde:** módulo con prefijo de nombres de ruta `bulk-operations` (definido en `Tuti/routes/admin.php`). **Audiencia:** administradores; no es un flujo de vendedor. Documentación complementaria (inglés) con el mismo listado de columnas CSV: [BULK_OPERATIONS.md](../../BULK_OPERATIONS.md).

## 1. Qué hace

1. Lanzar una **sincronización masiva** de datos de clientes contra el backend (SOAP / Rutero), iterando clientes con documento y lógica en `UserRepository::syncUserRuteroData` (vista de alto nivel: ver doc en inglés y el job encolado).
2. Gestionar **archivos de informe** (CSV) generados: descargar, borrar, auditar *skipped* o *error*.

La sincro masiva no reemplaza otras: **cada** checkout de carrito *también* puede forzar un `syncUserRuteroData` antes de procesar el pedido (ver *Bulk* doc, referencia a `CartController`).

## 2. Procedimiento operativo

1. Entrar al módulo “Procesos masivos” / *Bulk* (título de menú depende de la *skin*; la ruta lógica es el *prefix* de arriba).  
2. Revisar el resumen: cantidad de clientes afectada o mensaje de requisito (documento, conexión, etc., según implementación de la *index* y del job).  
3. Ejecutar el botón que dispara el `POST` `bulk-operations.sync-clients-data` (nombre exacto: ver *routes*), confirmar si pide *confirmación*.  
4. Volver a la misma pantalla: el **job** se ejecuta de forma asincrónica; mientras, puede haber *spinner* o *mensaje* de *session*.  
5. En la sección de **Reportes generados**: descargar el CSV, conservarlo según *política interna* (nómina de balance, *quota*, zonas) y, si aplica, eliminar con la acción que mapea a `bulk-operations.delete-report` para liberar disco.

> Si nada progresa: comprobar **colas** ([colas y Horizon](../../tecnica/colas-y-horizon.md)) y *failed_jobs*.

## 3. Contenido del CSV (referencia lógica)

Alineada con [BULK_OPERATIONS.md](../../BULK_OPERATIONS.md) — columnas como identificador de usuario, documento, estado de fila (`success`, `failed`, `skipped`, `error`), campos actualizados, cantidad o cambio de **zonas**, error textual y marca de tiempo. Cualquier cambio en el job *debe* reflejarse *primero* en el doc de inglés y, si impacta a usuarios, en este resumen en español.

## 4. Riesgos y gobernanza

- Carga a **SOAP** en *horas pico*: coordenar con sistemas.  
- Borrado de reporte **desde** la UI: suele ser **irreversible** en *storage*; verificar *backup* o descarga.  
- Resultados *skipped* por falta de documento: *no* es *bug* del job; requiere limpieza de *master data* o creación vía *admin* / *form*.

## 5. Dónde encaja con el resto

- [12 — Zonas y rutas](12-zonas-rutas-y-48-horas.md) (por qué faltan rutas)  
- [10 — Usuarios](10-usuarios-vendedores-y-accesos.md) (export, edición)  
- [tecnica: colas](../../tecnica/colas-y-horizon.md) — *workers* inactivos = *bulk* eterno

---

*Revisión: abril 2026.*
