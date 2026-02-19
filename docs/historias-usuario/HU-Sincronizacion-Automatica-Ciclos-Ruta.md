# HU Sincronización Automática de Ciclos de Ruta

Yo como administrador del sistema quiero que los ciclos de ruta se sincronicen automáticamente con el webservice diariamente durante la noche para mantener actualizada la información de rutas y sus ciclos asignados sin intervención manual. Esta funcionalidad será usada para:
- Mantener los ciclos de ruta actualizados automáticamente cuando cambien en el sistema externo
- Eliminar la necesidad de crear y actualizar ciclos de ruta manualmente
- Asegurar que los cálculos de fechas de entrega usen información actualizada de rutas y ciclos
- Reducir errores por desincronización entre el sistema y el webservice externo

## Criterios de aceptación

- La sincronización debe ejecutarse automáticamente una vez al día durante la noche, siguiendo el mismo patrón de sincronización que otros procesos existentes (como la sincronización de inventario)
- El proceso debe usar el método SOAP `GetZones` del webservice para obtener la lista completa de todas las zonas disponibles
- La llamada a `GetZones` debe realizarse sin filtros (campos `deliverDate` y `zone` vacíos) para obtener todas las zonas
- Para cada zona obtenida de `GetZones`, se debe calcular el ciclo correspondiente usando el método `getRutero` existente
- El proceso debe actualizar o crear registros en la tabla `route_cycles` con la información de ruta y ciclo obtenida
- Si una ruta ya existe en `route_cycles`, se debe actualizar su ciclo si ha cambiado
- Si una ruta no existe, se debe crear un nuevo registro con la ruta y su ciclo correspondiente
- El proceso debe manejar errores de manera robusta: si `GetZones` falla, debe registrar el error en logs y no afectar otros procesos
- El proceso debe manejar errores de manera robusta: si `getRutero` falla para una zona específica, debe continuar con las demás zonas y registrar el error
- El proceso debe usar el token de Microsoft existente y seguir el mismo patrón de validación y renovación de token que otros procesos de sincronización
- El proceso debe ejecutarse como un Job de Laravel que se envíe a la cola para procesamiento asíncrono
- El Job debe tener configurado timeout apropiado (similar a otros jobs de sincronización) y manejo de reintentos
- El proceso debe registrar en logs el inicio, progreso y finalización de la sincronización, incluyendo cuántas rutas se actualizaron o crearon
- El proceso debe respetar la configuración de habilitación/deshabilitación si se implementa un toggle de configuración (similar a `inventory_sync_enabled`)
- La sincronización debe ejecutarse en un horario que no interfiera con otros procesos críticos (recomendado: después de la sincronización de inventario)
- El proceso debe preservar los ciclos de ruta que fueron creados manualmente si no se encuentran en el webservice (opcional: solo actualizar, no eliminar)
- Si una ruta obtenida de `GetZones` no puede determinar su ciclo mediante `getRutero`, se debe registrar en logs pero no debe fallar todo el proceso
