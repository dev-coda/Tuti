# Módulo de Zonas y Rutas

## 📋 Descripción General

El módulo de Zonas y Rutas gestiona la asignación geográfica de clientes, las rutas de entrega y la relación entre zonas y bodegas de inventario.

## 🗺️ Concepto de Zona

Una zona es una área geográfica que determina:
- La bodega de inventario asignada
- Las fechas de entrega disponibles
- La ruta de entrega
- El día de la semana de entrega

### Estructura de Zona

Cada zona tiene:
- **Código**: Identificador único (ej: "926")
- **Zona**: Número de zona (ej: "933")
- **Ruta**: Ruta de entrega (ej: "RUTA-01")
- **Día**: Día de la semana (ej: "5-Viernes")
- **Dirección**: Dirección asociada
- **Usuario**: Usuario al que pertenece

## 🔄 Sincronización de Rutas

### Sincronización Automática

Las zonas se sincronizan automáticamente desde un sistema externo:

1. **Cuándo se Sincroniza**:
   - Al procesar una orden
   - Manualmente desde admin
   - Según configuración del sistema

2. **Proceso**:
   - Se consulta el sistema externo con el documento del usuario
   - Se obtienen rutas asignadas
   - Se actualizan zonas del usuario

3. **Actualización**:
   - Se actualizan zonas existentes
   - Se crean zonas nuevas
   - Se eliminan zonas obsoletas (solo si no tienen órdenes)

### Sincronización Manual

1. **Desde Admin**:
   - Panel Admin → Usuarios → Editar Usuario
   - Botón "Sincronizar Rutas"
   - Se ejecuta inmediatamente

2. **Desde API**:
   - Endpoint de sincronización
   - Útil para integraciones

## 🏭 Asignación de Bodegas

### Mapeo Zona-Bodega

Cada zona tiene una bodega asignada:

1. **Configuración**:
   - Panel Admin → Configuración → Zonas y Bodegas
   - Mapear zona → bodega

2. **Ejemplo**:
   - Zona 933 → Bodega A
   - Zona 934 → Bodega B
   - Zona 935 → Bodega A

### Determinación Automática

Al procesar una orden:
1. Se obtiene la zona del usuario
2. Se busca la bodega asignada a esa zona
3. Se valida inventario de esa bodega
4. Se usa esa bodega para la orden

## 🚚 Rutas y Ciclos

### Concepto de Ruta

Una ruta es un recorrido de entrega que tiene:
- **Código de Ruta**: Identificador único
- **Zonas Asignadas**: Zonas que cubre
- **Día de Entrega**: Día de la semana

### Ciclos de Ruta

Las rutas se organizan en ciclos (A, B, C):

1. **Configuración**:
   - Panel Admin → Ciclos de Ruta
   - Asignar rutas a ciclos

2. **Uso**:
   - Se usa para calcular fechas de entrega
   - Determina disponibilidad semanal

## 📅 Calendarios de Entrega

### Concepto

Los calendarios definen semanas disponibles para entrega según el ciclo:

1. **Estructura**:
   - Ciclo (A, B, C)
   - Semana (fecha inicio, fecha fin)
   - Estado (disponible/no disponible)

2. **Uso**:
   - Se usa para calcular fechas de entrega
   - Determina qué semanas están disponibles

### Gestión de Calendarios

1. **Crear Calendario**:
   - Panel Admin → Calendarios de Entrega → Crear
   - Seleccionar ciclo
   - Configurar semana (inicio, fin)
   - Marcar como disponible

2. **Importar desde CSV**:
   - Panel Admin → Calendarios de Entrega → Importar
   - Descargar plantilla
   - Completar y subir

3. **Exportar Plantilla**:
   - Descargar plantilla CSV
   - Útil para importación masiva

## 🔧 Gestión de Zonas (Administradores)

### Ver Zonas de Usuario

1. **Panel Admin → Usuarios → Editar Usuario**
2. **Pestaña "Zonas"**
3. **Muestra**:
   - Todas las zonas del usuario
   - Código, zona, ruta, día
   - Bodega asignada

### Sincronizar Zonas Manualmente

1. **Panel Admin → Usuarios → Editar Usuario**
2. **Botón "Sincronizar Rutas"**
3. **Resultado**:
   - Se actualizan zonas desde sistema externo
   - Se muestran cambios realizados

### Asignar Zona Manualmente

En casos especiales, se puede asignar zona manualmente:

1. **Panel Admin → Usuarios → Editar Usuario**
2. **Agregar Zona Manualmente**
3. **Configurar**:
   - Código
   - Zona
   - Ruta
   - Día
   - Dirección

⚠️ **Nota**: Las zonas manuales pueden sobrescribirse con sincronización.

## 📊 Reglas de Negocio

### Múltiples Zonas por Usuario

Un usuario puede tener múltiples zonas:
- Diferentes direcciones
- Diferentes rutas
- Al crear orden, selecciona la zona deseada

### Zona y Bodega

- Cada zona tiene una bodega asignada
- La bodega determina el inventario disponible
- Se valida stock de la bodega asignada

### Sincronización y Órdenes

- Si una zona tiene órdenes, no se puede eliminar
- Se actualiza en lugar de eliminar
- Se mantiene historial de órdenes

## ⚠️ Consideraciones Importantes

### Sincronización Automática

- Se ejecuta al procesar órdenes
- Puede cambiar zonas del usuario
- Verificar zonas después de sincronización

### Zonas Obsoletas

- Si una zona ya no existe en el sistema externo:
  - Se marca como obsoleta (si no tiene órdenes)
  - Se mantiene si tiene órdenes
  - Se puede eliminar manualmente si es necesario

### Cambios en Bodegas

- Si cambia la asignación zona-bodega:
  - Afecta nuevas órdenes
  - Las órdenes existentes mantienen bodega original
  - Verificar inventario después de cambios

## ❓ Preguntas Frecuentes

### ¿Por qué mi usuario no tiene zonas?

Puede ser porque:
- No se ha sincronizado desde el sistema externo
- El documento no está registrado en el sistema externo
- Hay un error en la sincronización

### ¿Puedo tener múltiples zonas?

Sí, un usuario puede tener múltiples zonas. Selecciona la zona deseada al crear una orden.

### ¿Qué pasa si cambio la bodega de una zona?

Los cambios afectan nuevas órdenes. Las órdenes existentes mantienen la bodega original.

### ¿Cómo sincronizo las zonas de un usuario?

Panel Admin → Usuarios → Editar Usuario → Botón "Sincronizar Rutas".

### ¿Qué pasa si una zona desaparece del sistema externo?

Si no tiene órdenes, se elimina. Si tiene órdenes, se mantiene para historial.

