# Módulo de Inventario

## 📋 Descripción General

El módulo de Inventario gestiona el stock de productos por bodega, incluyendo sincronización automática con sistemas externos, stock de seguridad y validaciones de disponibilidad.

## 🏭 Bodegas

### Concepto de Bodega

Una bodega es una ubicación física donde se almacenan productos. El sistema soporta múltiples bodegas y asigna productos a bodegas según la zona del cliente.

### Asignación de Bodegas por Zona

- Cada zona geográfica tiene una bodega asignada
- Se configura en Panel Admin → Configuración → Zonas y Bodegas
- Al procesar una orden, se usa la bodega de la zona del cliente

## 📦 Gestión de Inventario

### Productos con Gestión de Inventario

#### Activar Gestión de Inventario

1. **A Nivel Global**
   - Panel Admin → Configuración → Inventario
   - Activar/desactivar gestión global
   - Si está desactivada, ningún producto gestiona inventario

2. **A Nivel de Producto**
   - Al crear/editar producto
   - Checkbox "Gestionar Inventario"
   - Puede desactivarse por producto aunque esté activo globalmente

#### Productos sin Gestión de Inventario

- No se valida stock
- Siempre disponibles
- Útil para servicios o productos especiales
- No aparecen en reportes de inventario

### Stock por Bodega

Cada producto tiene inventario independiente por bodega:

- **Bodega A**: 100 unidades
- **Bodega B**: 50 unidades
- **Bodega C**: 0 unidades

Al comprar, se valida el stock de la bodega asignada a la zona del cliente.

### Campos de Inventario

1. **Disponible** (`available`)
   - Stock disponible para venta
   - Se decrementa al procesar orden
   - Se incrementa con sincronización

2. **Físico** (`physical`)
   - Stock físico real en bodega
   - Se actualiza con sincronización
   - Puede diferir de disponible

3. **Reservado** (`reserved`)
   - Stock reservado en órdenes pendientes
   - Se libera si se cancela la orden
   - Se decrementa al procesar

### Stock de Seguridad

#### Configuración

1. **Global**
   - Panel Admin → Configuración → Inventario
   - Stock de seguridad por defecto

2. **Por Producto**
   - Al crear/editar producto
   - Campo "Stock de Seguridad"
   - Sobrescribe el valor global

#### Funcionamiento

- Si el stock disponible está por debajo del stock de seguridad:
  - Se muestra alerta en admin
  - Se bloquea la venta (configurable)
  - Aparece en reportes de bajo stock

## 🔄 Sincronización de Inventario

### Sincronización Automática

El sistema sincroniza inventario automáticamente con un sistema externo vía SOAP:

1. **Frecuencia**
   - Se ejecuta automáticamente cada noche
   - Configurable en Panel Admin → Configuración

2. **Proceso**
   - Obtiene inventario por bodega desde sistema externo
   - Actualiza stock disponible y físico
   - Registra productos no encontrados (se ponen en 0)

3. **Logs**
   - Se registran todas las sincronizaciones
   - Disponible en Panel Admin → Configuración → Logs de Inventario
   - Incluye respuesta completa del servicio

### Sincronización Manual

1. **Desde Admin**
   - Panel Admin → Configuración → Logs de Inventario
   - Botón "Sincronizar Ahora"
   - Opción síncrona (espera) o asíncrona (cola)

2. **Desde Comando**
   ```bash
   php artisan inventory:sync
   ```

### Proceso de Sincronización

1. **Por Bodega**
   - Se procesa cada bodega por separado
   - Se obtiene lista de SKUs con stock

2. **Actualización**
   - Productos encontrados: se actualiza stock
   - Productos no encontrados: se pone en 0
   - Se registra cantidad de productos actualizados

3. **Registro**
   - Se guarda log de cada sincronización
   - Incluye estadísticas:
     - SKUs recibidos
     - Productos actualizados
     - Productos puestos en 0
     - Estado (éxito/error)

## 📊 Validación de Inventario

### Al Agregar al Carrito

1. **Verificación Inicial**
   - Se verifica disponibilidad
   - Se muestra mensaje si no hay stock
   - No bloquea agregar al carrito (permite reservar)

### Al Procesar Orden

1. **Validación Completa**
   - Verifica stock disponible
   - Verifica stock de seguridad
   - Verifica cantidad solicitada vs disponible
   - Considera stock reservado

2. **Bloqueos**
   - Si está por debajo de stock de seguridad: bloquea
   - Si cantidad excede disponible: bloquea
   - Si no hay stock: bloquea

3. **Reserva**
   - Si pasa validación, reserva inventario
   - Se decrementa al procesar
   - Se libera si se cancela

### Productos con Variaciones

- Si la variación **no** tiene SKU propio en Dynamics, el stock suele vivir a nivel producto padre
- Si la variación tiene **SKU propio**, la sincronización puede mantener inventario **por variación** (`variation_item_id`); el checkout consulta ese pool
- En vitrina, el stock “pedible” puede mostrar el máximo entre variaciones habilitadas
- No asumir que “todas las variaciones siempre comparten el mismo stock”: depende de cómo estén cargados los SKU en Dynamics y en el producto

### Al Procesar Orden — mensajes al comprador

Los mensajes de error nombran cantidades y el piso aplicable:

- Línea pagada bajo piso: *«… está por debajo del stock de seguridad (X disponibles, mínimo: Y)»* o *mínimo global de inventario*
- Cantidad excesiva: *«… (N solicitadas, M disponibles)»*
- Obsequio de bonificación: mensaje distinto que nombra el **producto de regalo** (ver [08 — Bonificaciones](./08-bonificaciones.md))

### Logs de Sincronización

- Panel Admin → Configuración → Logs de Inventario
- Muestra últimas sincronizaciones (fecha, bodega, productos, estado, respuesta)
- **Bodegas no sincronizadas ayer**: aviso si alguna bodega no completó sync el día anterior
- Tras fallos, el sistema puede **reintentar** sincronizaciones de bodega con retraso (p. ej. ~1 h) y reportar omisiones del día previo

## 📈 Reportes de Inventario

### Productos con Bajo Stock

- Lista de productos bajo stock de seguridad
- Filtrable por bodega
- Útil para reposición

### Movimiento de Inventario

- Historial de cambios de stock
- Incluye órdenes procesadas
- Incluye sincronizaciones

### Logs de Sincronización (detalle)

Ver sección anterior: pantalla `settings/inventory-logs`, incluyendo el aviso de bodegas sin sync de ayer.

## ⚙️ Configuración

### Activar/Desactivar Inventario Global

1. **Panel Admin → Configuración → Inventario**
2. **Toggle "Gestión de Inventario Habilitada"**
3. **Guardar**

Si está desactivada:
- No se valida stock en ningún producto
- Todos los productos aparecen disponibles
- No se sincroniza inventario

### Configurar Stock de Seguridad Global

1. **Panel Admin → Configuración → Inventario**
2. **Campo "Stock de Seguridad por Defecto"**
3. **Valor numérico**
4. **Guardar**

Este valor se usa para productos que no tienen stock de seguridad específico.

### Configurar Sincronización Automática

1. **Panel Admin → Configuración → Inventario**
2. **Toggle "Sincronización Automática Habilitada"**
3. **Hora de sincronización** (si aplica)
4. **Guardar**

## 🔧 Mantenimiento

### Actualizar Stock Manualmente

1. **Panel Admin → Productos → Editar Producto**
2. **Pestaña "Inventario"**
3. **Editar valores por bodega**
4. **Guardar**

⚠️ **Nota**: Los cambios manuales pueden sobrescribirse con la próxima sincronización.

### Limpiar Stock Reservado

Si hay stock reservado "perdido" (órdenes canceladas que no liberaron stock):

1. **Panel Admin → Inventario → Limpiar Reservas**
2. **Confirmar acción**
3. **Se libera stock reservado de órdenes canceladas**

## 📝 Reglas de Negocio

### Cálculo de Disponible

```
Disponible = Físico - Reservado
```

- El disponible es lo que realmente se puede vender
- Se reserva al crear orden
- Se decrementa al procesar

### Validación de Stock de Seguridad

Si `Disponible <= Stock de Seguridad`:
- Se muestra alerta
- Se puede bloquear venta (configurable)
- Aparece en reportes

### Productos No Encontrados en Sincronización

- Si un producto no aparece en la respuesta del servicio:
- Se pone stock en 0 para esa bodega
- Se registra en logs
- Se puede revisar en logs de sincronización

### Múltiples Bodegas

- Un producto puede tener stock en múltiples bodegas
- Cada bodega es independiente
- Al comprar, se usa la bodega de la zona del cliente

## ⚠️ Consideraciones Importantes

### Sincronización vs Manual

- Los cambios manuales pueden sobrescribirse con sincronización
- La sincronización es la fuente de verdad
- Usar cambios manuales solo para correcciones temporales

### Stock Negativo

- El sistema no permite stock negativo
- Si se intenta vender más de lo disponible, bloquea
- Se valida antes de procesar orden

### Órdenes Pendientes

- El stock se reserva al crear orden
- Se libera si se cancela
- Se decrementa al procesar
- Si hay muchas órdenes pendientes, el disponible puede ser menor

## ❓ Preguntas Frecuentes

### ¿Por qué un producto muestra "No disponible" si tiene stock?

Puede ser porque:
- El stock está reservado en órdenes pendientes
- Está por debajo del stock de seguridad (si está configurado para bloquear)
- La bodega asignada a tu zona no tiene stock

### ¿Cómo actualizo el inventario de un producto?

Puedes:
- Esperar la sincronización automática
- Sincronizar manualmente desde admin
- Actualizar manualmente desde edición de producto (temporal)

### ¿Qué pasa si sincronizo y un producto desaparece?

Si el producto no aparece en la respuesta del servicio:
- Se pone stock en 0 para esa bodega
- Se registra en logs
- El producto sigue existiendo, solo sin stock

### ¿Puedo tener diferentes stocks de seguridad por bodega?

No, el stock de seguridad es por producto, no por bodega. Pero puedes tener diferentes stocks disponibles por bodega.

### ¿Cómo veo el historial de cambios de inventario?

Actualmente no hay un historial detallado. Los cambios se registran en logs de sincronización y en el procesamiento de órdenes.

