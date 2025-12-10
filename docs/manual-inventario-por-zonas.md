# Manual de Usuario: Sistema de Inventario por Zonas

## Tabla de Contenidos
1. [Introducción](#introducción)
2. [Conceptos Básicos](#conceptos-básicos)
3. [Configuración del Sistema](#configuración-del-sistema)
4. [Gestión de Zonas y Bodegas](#gestión-de-zonas-y-bodegas)
5. [Gestión de Inventario](#gestión-de-inventario)
6. [Stock de Seguridad](#stock-de-seguridad)
7. [Proceso de Ventas](#proceso-de-ventas)
8. [Sincronización Automática](#sincronización-automática)
9. [Casos de Uso](#casos-de-uso)
10. [Solución de Problemas](#solución-de-problemas)
11. [Documentación Técnica](#documentación-técnica)

---

## Introducción

El Sistema de Inventario por Zonas de Tuti permite gestionar el inventario de productos de manera diferenciada según la ubicación geográfica de los clientes. Este sistema asegura que:

- Los clientes solo vean productos disponibles en su zona
- Se mantenga un control preciso del inventario por ubicación
- Se respeten los niveles mínimos de stock de seguridad
- Se sincronice automáticamente con sistemas externos (Microsoft Dynamics)

El sistema es completamente opcional y configurable, permitiendo que ciertos productos o categorías se excluyan de la gestión de inventario.

---

## Conceptos Básicos

### Zonas
Las **zonas** son áreas geográficas donde operan los clientes y vendedores. Cada usuario del sistema pertenece a una o más zonas específicas.

**Características de las zonas**:
- Tienen un código único identificador
- Se asocian con rutas de entrega específicas
- Incluyen información de dirección y contacto
- Se vinculan directamente con usuarios

### Bodegas
Las **bodegas** son centros de almacenamiento físico donde se mantiene el inventario de productos. Cada bodega tiene un código único y puede atender a una o más zonas.

**Características de las bodegas**:
- Código único (ej: "MDTAT", "BOG01", "MED02")
- Inventario independiente por producto
- Puede atender múltiples zonas
- Se sincroniza con sistemas externos

### Mapeo Zona-Bodega
El **mapeo zona-bodega** es la configuración que determina qué bodega atiende a cada zona específica. Esta relación es fundamental para:
- Determinar disponibilidad de productos por zona
- Calcular inventario disponible para cada cliente
- Procesar órdenes correctamente

### Stock de Seguridad
El **stock de seguridad** es la cantidad mínima de inventario que debe mantenerse siempre disponible. Los productos no se pueden vender si esto haría que el inventario caiga por debajo de este nivel.

---

## Configuración del Sistema

### Habilitación del Sistema de Inventario

1. **Acceso a configuración**:
   - Ir a **Configuración** > **Inventario** en el panel administrativo
   - Localizar la opción "Gestión de Inventario Habilitada"

2. **Activación**:
   - Marcar la casilla para activar el sistema
   - Guardar cambios

3. **Verificación**:
   - Confirmar que aparece el indicador de "Inventario Activo" en el dashboard
   - Verificar que los productos muestran información de inventario

**Importante**: Cuando el sistema está deshabilitado, no se realizan validaciones de inventario y todos los productos se consideran disponibles.

### Configuración Global

#### Parámetros del Sistema
- **Inventario mínimo**: 5 unidades (por defecto)
- **Sincronización automática**: Configurable por intervalos
- **Token de Microsoft**: Requerido para sincronización externa

#### Exclusiones del Sistema
Algunos elementos pueden excluirse automáticamente:
- Productos de la categoría "OFERTAS"
- Productos con variaciones activas
- Productos marcados específicamente para exclusión

---

## Gestión de Zonas y Bodegas

### Crear y Configurar Zonas

#### Información Básica de Zona
1. **Código de zona**: Identificador único alfanumérico
2. **Nombre descriptivo**: Nombre legible de la zona
3. **Ruta**: Información de ruta de entrega
4. **Dirección**: Ubicación física de la zona
5. **Día de entrega**: Día de la semana para entregas

#### Asignación de Usuarios a Zonas
1. **Acceso**: Panel de usuarios > Editar usuario
2. **Sección de zonas**: Agregar o modificar zonas asignadas
3. **Información por zona**:
   - Código de rutero personalizado
   - Dirección específica
   - Día de entrega preferido

### Mapeo de Zona a Bodega

#### Configuración Manual
1. **Acceso**: **Inventario** > **Mapeo Zona-Bodega**
2. **Crear mapeo**:
   - Seleccionar código de zona
   - Asignar código de bodega
   - Confirmar la configuración

#### Configuración por Archivo
Para múltiples mapeos, se puede usar el archivo de configuración:
```php
// config/zone_warehouses.php
'mappings' => [
    'BOG01' => 'MDTAT',
    'MED01' => 'MEDEL',
    'CALI01' => ['CALI1', 'CALI2'], // Múltiples bodegas
]
```

### Gestión de Bodegas

#### Información de Bodega
- **Código único**: Identificador en el sistema externo
- **Nombre descriptivo**: Para identificación interna
- **Estado**: Activa o inactiva
- **Capacidad**: Información opcional

#### Sincronización con Sistema Externo
Las bodegas se sincronizan automáticamente con Microsoft Dynamics, obteniendo:
- Inventario físico actual
- Inventario reservado
- Movimientos de inventario
- Actualizaciones en tiempo real

---

## Gestión de Inventario

### Visualización de Inventario

#### Por Producto
1. **Acceso**: **Productos** > Seleccionar producto
2. **Sección de inventario**:
   - Inventario por bodega
   - Stock de seguridad configurado
   - Estado de disponibilidad

#### Por Bodega
1. **Acceso**: **Inventario** > **Por Bodega**
2. **Información disponible**:
   - Lista de productos en la bodega
   - Cantidades disponibles
   - Niveles de stock de seguridad
   - Productos por debajo del mínimo

### Estados de Inventario

#### Disponible
- **Verde**: Inventario suficiente, por encima del stock de seguridad
- **Productos visibles**: Se muestran normalmente a los clientes
- **Compras permitidas**: Se pueden procesar órdenes

#### Stock Bajo
- **Amarillo**: Inventario cerca del stock de seguridad
- **Advertencia**: Se muestra alerta en el sistema
- **Compras limitadas**: Se puede vender con restricciones

#### Stock de Seguridad
- **Rojo**: Inventario igual o por debajo del stock de seguridad
- **Productos ocultos**: No se muestran a los clientes
- **Compras bloqueadas**: No se pueden procesar órdenes

#### Sin Inventario
- **Gris**: Inventario en cero
- **Producto no disponible**: Completamente oculto para clientes
- **Reabastecimiento requerido**: Necesita reposición urgente

### Ajustes Manuales de Inventario

#### Casos para Ajustes Manuales
- Corrección de discrepancias
- Daños o pérdidas
- Ajustes por inventario físico
- Correcciones de sistema

#### Proceso de Ajuste
1. **Acceso**: **Inventario** > **Ajustes**
2. **Selección**:
   - Producto específico
   - Bodega afectada
3. **Tipo de ajuste**:
   - Incremento de inventario
   - Decremento de inventario
   - Ajuste absoluto
4. **Justificación**:
   - Motivo del ajuste
   - Comentarios adicionales
5. **Confirmación**: Revisar y aplicar cambios

---

## Stock de Seguridad

### Configuración de Stock de Seguridad

#### Nivel de Producto
1. **Acceso**: Editar producto > Sección de inventario
2. **Stock de seguridad**: Ingresar cantidad mínima
3. **Prioridad**: Tiene precedencia sobre configuración de categoría

#### Nivel de Categoría
1. **Acceso**: Editar categoría > Configuración de inventario
2. **Stock de seguridad por defecto**: Aplica a todos los productos sin configuración específica
3. **Herencia**: Los productos nuevos toman este valor automáticamente

### Estrategias de Stock de Seguridad

#### Por Velocidad de Rotación
- **Productos de alta rotación**: Stock de seguridad más alto (15-30 unidades)
- **Productos de rotación media**: Stock moderado (5-15 unidades)
- **Productos de baja rotación**: Stock mínimo (2-5 unidades)

#### Por Temporada
- **Productos estacionales**: Ajustar según época del año
- **Productos de demanda constante**: Stock estable todo el año
- **Productos promocionales**: Stock temporal más alto

#### Por Importancia Estratégica
- **Productos estrella**: Stock de seguridad alto para evitar desabastecimiento
- **Productos de margen alto**: Proteger disponibilidad
- **Productos de fidelización**: Mantener siempre disponibles

### Alertas de Stock de Seguridad

#### Notificaciones Automáticas
- **Email diario**: Resumen de productos bajo stock de seguridad
- **Alertas en tiempo real**: Cuando un producto alcanza el límite
- **Dashboard de inventario**: Vista consolidada de alertas

#### Acciones Recomendadas
1. **Revisar demanda**: Analizar si el stock de seguridad es apropiado
2. **Reordenar**: Gestionar reposición con proveedores
3. **Ajustar configuración**: Modificar niveles si es necesario

---

## Proceso de Ventas

### Validación de Inventario en Ventas

#### Al Agregar al Carrito
1. **Verificación automática**:
   - Determinar zona del cliente
   - Identificar bodega correspondiente
   - Validar inventario disponible
   - Verificar stock de seguridad

2. **Comportamiento según inventario**:
   - **Disponible**: Producto se agrega normalmente
   - **Stock de seguridad**: Mensaje de error, no se agrega
   - **Sin inventario**: Producto no visible o no agregable

#### Durante el Checkout
1. **Validación final**:
   - Revalidar todo el carrito
   - Verificar que no hay cambios en inventario
   - Confirmar disponibilidad por zona

2. **Manejo de conflictos**:
   - Si un producto ya no está disponible: mensaje de error
   - Sugerencias de productos alternativos
   - Opción de proceder sin el producto

### Reserva de Inventario

#### Durante el Proceso de Compra
- **Reserva temporal**: Al iniciar el checkout
- **Duración**: 10 minutos para completar la compra
- **Liberación automática**: Si no se completa en el tiempo límite

#### Al Confirmar la Orden
- **Descuento definitivo**: Se reduce el inventario disponible
- **Actualización inmediata**: Reflejo en tiempo real
- **Sincronización**: Envío a sistema externo

### Casos Especiales

#### Vendedores
- Pueden seleccionar la zona del cliente
- Acceso a inventario de múltiples zonas
- Validaciones específicas por zona seleccionada

#### Productos sin Gestión de Inventario
- **Categoría "OFERTAS"**: Excluida automáticamente
- **Productos con variaciones**: No gestionados por inventario
- **Exclusión manual**: Productos marcados específicamente

---

## Sincronización Automática

### Integración con Microsoft Dynamics

#### Configuración de Conexión
1. **Token de acceso**: Configuración en variables del sistema
2. **Actualización automática**: Renovación cada 2 minutos
3. **Endpoints**: URLs específicas para cada bodega

#### Datos Sincronizados
- **Inventario físico**: Cantidad real en bodega
- **Inventario reservado**: Cantidad comprometida
- **Movimientos**: Entradas y salidas
- **Actualizaciones**: Cambios en tiempo real

### Proceso de Sincronización

#### Frecuencia
- **Automática**: Cada 15 minutos durante horario comercial
- **Manual**: Disponible desde el panel administrativo
- **En tiempo real**: Para movimientos críticos

#### Validaciones
1. **Conexión activa**: Verificar acceso al sistema externo
2. **Token válido**: Confirmar autenticación
3. **Datos consistentes**: Validar información recibida
4. **Errores de red**: Manejo de fallos de conexión

#### Manejo de Errores
- **Reintentos automáticos**: 3 intentos con intervalos crecientes
- **Logging**: Registro detallado de errores
- **Notificaciones**: Alertas al equipo técnico
- **Modo de contingencia**: Continuar operación con últimos datos válidos

### Monitoreo de Sincronización

#### Dashboard de Estado
- **Última sincronización**: Timestamp de la última actualización exitosa
- **Estado de conexión**: Verde/Rojo según conectividad
- **Errores recientes**: Lista de problemas encontrados
- **Estadísticas**: Número de productos sincronizados

#### Logs de Sincronización
- **Registro detallado**: Cada operación de sincronización
- **Filtros**: Por fecha, bodega, producto
- **Alertas**: Configurables por tipo de evento

---

## Casos de Uso

### Caso 1: Cliente de Bogotá Realiza Compra

**Escenario**: Cliente con zona BOG01 intenta comprar 10 unidades de un producto

**Proceso**:
1. Sistema identifica zona BOG01 del cliente
2. Consulta mapeo: BOG01 → Bodega MDTAT
3. Verifica inventario en MDTAT: 25 unidades disponibles
4. Verifica stock de seguridad: 5 unidades configuradas
5. Calcula disponible para venta: 25 - 5 = 20 unidades
6. Como 10 < 20, permite la compra
7. Al confirmar orden: reduce inventario a 15 unidades

### Caso 2: Producto con Stock Insuficiente

**Escenario**: Cliente intenta comprar producto con inventario bajo

**Proceso**:
1. Cliente agrega producto al carrito
2. Sistema verifica: Inventario = 3, Stock de seguridad = 5
3. Como 3 < 5, se muestra mensaje: "Este producto no está disponible por debajo del stock de seguridad"
4. Producto no se agrega al carrito
5. Se sugieren productos alternativos

### Caso 3: Vendedor Trabajando con Múltiples Zonas

**Escenario**: Vendedor atiende clientes de diferentes zonas

**Proceso**:
1. Vendedor selecciona cliente de zona MED01
2. Sistema cambia contexto a bodega de Medellín
3. Productos muestran inventario específico de esa bodega
4. Al cambiar a cliente de BOG01, inventario se actualiza automáticamente
5. Validaciones de stock se basan en la zona del cliente actual

### Caso 4: Sincronización con Fallo

**Escenario**: Falla temporal en conexión con Microsoft Dynamics

**Proceso**:
1. Job de sincronización detecta fallo de conexión
2. Sistema registra error en logs
3. Reintenta después de 5 minutos
4. Mientras tanto, usa últimos datos sincronizados
5. Cuando se restaura conexión, sincroniza cambios pendientes
6. Notifica al equipo técnico si persisten los fallos

---

## Solución de Problemas

### Problemas Comunes

#### "Producto no disponible por debajo del stock de seguridad"
**Causa**: Inventario insuficiente para la venta
**Solución**:
1. Verificar inventario real en bodega
2. Revisar configuración de stock de seguridad
3. Ajustar stock de seguridad si es muy alto
4. Reabastecer producto si es necesario

#### "No se pudo determinar la bodega para su zona"
**Causa**: Zona del cliente no tiene bodega asignada
**Solución**:
1. Verificar mapeo zona-bodega en configuración
2. Crear mapeo faltante
3. Verificar que el código de zona es correcto
4. Revisar configuración del usuario

#### Productos no visibles para clientes
**Causa**: Problemas de inventario o configuración
**Diagnóstico**:
1. Verificar si el inventario está habilitado globalmente
2. Confirmar que el producto no está excluido
3. Revisar inventario en la bodega correspondiente
4. Verificar stock de seguridad configurado

#### Sincronización fallando constantemente
**Causa**: Problemas de conectividad o configuración
**Solución**:
1. Verificar token de Microsoft Dynamics
2. Confirmar conectividad de red
3. Revisar logs de error detallados
4. Contactar equipo técnico si persiste

### Herramientas de Diagnóstico

#### Comando de Verificación de Inventario
```bash
php artisan inventory:check --product=123 --zone=BOG01
```

#### Reporte de Estado del Sistema
- **Panel de administración** > **Inventario** > **Estado del Sistema**
- Muestra estado de todas las integraciones
- Lista productos con problemas
- Estadísticas de sincronización

#### Logs del Sistema
- **Ubicación**: storage/logs/inventory.log
- **Información**: Errores, sincronizaciones, cambios de inventario
- **Filtros**: Por fecha, bodega, producto

---

## Documentación Técnica

### Arquitectura del Sistema

#### Modelos Principales

**Product**
```php
Campos relacionados con inventario:
- safety_stock: Stock de seguridad específico del producto
- inventory_opt_out: Exclusión de gestión de inventario
Métodos:
- isInventoryManaged(): Determina si está bajo gestión
- getEffectiveSafetyStock(): Calcula stock de seguridad efectivo
- getInventoryForBodega($bodegaCode): Obtiene inventario para bodega específica
```

**ProductInventory**
```php
Campos:
- product_id: Referencia al producto
- bodega_code: Código de la bodega
- available: Inventario disponible
- physical: Inventario físico
- reserved: Inventario reservado
```

**Zone**
```php
Campos:
- route: Información de ruta
- zone: Nombre/código de zona  
- day: Día de entrega
- address: Dirección
- code: Código único de zona
- user_id: Usuario propietario
```

**ZoneWarehouse**
```php
Campos:
- zone_code: Código de zona
- bodega_code: Código de bodega
Mapea qué bodega atiende cada zona
```

#### Configuración

**Settings relevantes:**
- `inventory_enabled`: Habilita/deshabilita el sistema
- `microsoft_token`: Token para integración externa
- `inventory_sync_enabled`: Habilita sincronización automática

**Archivo de configuración:**
```php
// config/zone_warehouses.php
return [
    'mappings' => [
        'BOG01' => 'MDTAT',
        'MED01' => 'MEDEL',
        // ...
    ]
];
```

### Flujo de Validación de Inventario

#### Al Agregar al Carrito
```php
1. Verificar si inventory_enabled = true
2. Verificar si product.isInventoryManaged() = true
3. Obtener zona del usuario: user->zones()->first()->code
4. Mapear zona a bodega: ZoneWarehouse o config
5. Obtener inventario: ProductInventory.available
6. Verificar safety_stock: product.getEffectiveSafetyStock()
7. Validar: available > safety_stock
8. Si válido: permitir agregado
9. Si inválido: mostrar error
```

#### Al Procesar Orden
```php
1. Revalidar todo el carrito
2. Usar lockForUpdate() para evitar race conditions
3. Verificar disponibilidad actual
4. Decrementar inventario: available = available - quantity
5. Actualizar ProductInventory
6. Registrar movimiento en logs
```

### Sincronización con Microsoft Dynamics

#### Job: SyncProductInventory
```php
Frecuencia: Configurabe (default: cada 15 min)
Proceso:
1. Verificar inventory_enabled
2. Obtener/renovar microsoft_token
3. Para cada bodega en ZoneWarehouse:
   - Consultar endpoint de Microsoft
   - Obtener datos de inventario
   - Actualizar/crear registros ProductInventory
4. Manejar errores y logging
```

#### Endpoints Microsoft
```
Base URL: Configurado en microsoft_token
Endpoints por bodega:
- /inventory/{bodega_code}/products
- /inventory/{bodega_code}/movements
```

### Jerarquía de Stock de Seguridad

```php
1. product.safety_stock (si no es null)
2. category.safety_stock (de la primera categoría)  
3. 0 (valor por defecto)

Implementación en Product.getEffectiveSafetyStock():
if (!is_null($this->safety_stock)) {
    return (int) $this->safety_stock;
}
$category = $this->categories->first();
return (int) ($category?->safety_stock ?? 0);
```

### Exclusiones de Inventario

#### Automáticas
```php
1. inventory_enabled = false (global)
2. product.inventory_opt_out = true
3. category.inventory_opt_out = true  
4. category.name = 'OFERTAS' (case insensitive)
```

**Nota:** Los productos con variaciones ahora respetan la configuración `inventory_opt_out` 
del producto individual, en lugar de ser excluidos automáticamente. Esto permite controlar 
la gestión de inventario para productos con variaciones de forma granular.

#### Verificación
```php
Product.isInventoryManaged():
- Verificar configuración global
- Verificar exclusión a nivel producto
- Verificar exclusión a nivel categoría
- Verificar nombre de categoría 'OFERTAS'
```

### Determinación de Bodega por Usuario

#### Algoritmo
```php
1. user->zones()->first()->code (zona primaria)
2. ZoneWarehouse.where('zone_code', $zoneCode)->value('bodega_code')
3. Si no existe: config('zone_warehouses.mappings')[$zoneCode]
4. Si vendedor: intentar con user_id de session
5. Fallback: buscar primera zona válida del usuario
6. Si todo falla: error "No se pudo determinar bodega"
```

### Performance y Optimización

#### Índices Recomendados
```sql
CREATE INDEX idx_product_inventory_product_bodega ON product_inventories(product_id, bodega_code);
CREATE INDEX idx_zone_warehouse_zone ON zone_warehouses(zone_code);  
CREATE INDEX idx_zones_user_code ON zones(user_id, code);
```

#### Caching
- Mapeos zona-bodega: Cache de 1 hora
- Configuración de inventario: Cache hasta reinicio
- Estados de productos: No cacheable (tiempo real)

#### Jobs y Queues
- SyncProductInventory: Queue 'inventory' con retry 3 veces
- Timeout: 300 segundos por sincronización
- Failover: Continuar con datos anteriores en caso de fallo
