# Módulo de Bonificaciones

## 📋 Descripción General

Las bonificaciones son promociones del tipo "Compra X, Lleva Y" que se aplican automáticamente cuando el cliente cumple con las condiciones establecidas.

## 🎁 Concepto de Bonificación

### Modelo "Compra X, Lleva Y"

- **Compra**: Cantidad mínima a comprar del producto
- **Lleva**: Cantidad que se regala
- **Ejemplo**: Compra 12, Lleva 2 → Al comprar 12 unidades, recibes 2 gratis

### Aplicación Automática

- Se calculan automáticamente al agregar productos al carrito
- Se agregan automáticamente como productos adicionales
- No requieren código de cupón
- Se muestran como "Bonificación" en el carrito

## ➕ Crear Bonificación

### Paso 1: Información Básica

1. **Panel Admin → Bonificaciones → Crear Bonificación**

2. **Datos Requeridos**:
   - **Producto**: Producto que activa la bonificación
   - **Producto Bonificación**: Producto que se regala
   - **Compra**: Cantidad mínima a comprar
   - **Lleva**: Cantidad que se regala
   - **Límite Máximo**: Cantidad máxima de bonificaciones por orden (opcional)

### Paso 2: Configuración Avanzada

1. **Permitir Descuentos**
   - **Sí**: Los descuentos se aplican normalmente
   - **No**: Se bloquean TODOS los descuentos si aplica esta bonificación

2. **Estado**
   - **Activa**: La bonificación está vigente
   - **Inactiva**: No se aplica (útil para desactivar temporalmente)

### Paso 3: Guardar

- Se guarda la bonificación
- Está lista para aplicarse automáticamente

## 🔢 Cálculo de Bonificaciones

### Fórmula

```
Bonificaciones = floor(Cantidad Comprada / Compra) * Lleva
```

### Ejemplos

1. **Compra 12, Lleva 2**
   - Compras 12 unidades → Recibes 2 gratis
   - Compras 24 unidades → Recibes 4 gratis
   - Compras 30 unidades → Recibes 4 gratis (floor de 2.5 = 2)

2. **Compra 6, Lleva 1**
   - Compras 6 unidades → Recibes 1 gratis
   - Compras 12 unidades → Recibes 2 gratis
   - Compras 7 unidades → Recibes 1 gratis (floor de 1.16 = 1)

### Agregación por Producto

Si compras el mismo producto en múltiples líneas del carrito, se suman las cantidades:

- Línea 1: 6 unidades
- Línea 2: 6 unidades
- Total: 12 unidades → Se calcula bonificación sobre 12

### Cantidad por Empaque

Se considera la cantidad por empaque del producto:

- Si el producto tiene `package_quantity = 6`
- Y compras 1 unidad (que son 6 individuales)
- Se calcula sobre 6 unidades individuales

## 🚫 Límites de Bonificación

### Límite Máximo

Si se configura un límite máximo:

- **Ejemplo**: Compra 12, Lleva 2, Límite máximo: 4
- Compras 60 unidades → Calculas 10 bonificaciones
- Pero el límite es 4 → Solo recibes 4 bonificaciones

### Sin Límite

Si no se configura límite máximo:
- No hay restricción
- Se calcula según la fórmula

## 💰 Bonificaciones y Descuentos

### Permitir Descuentos (allow_discounts = true)

- Los descuentos se aplican normalmente
- La bonificación se agrega adicionalmente
- Ejemplo: Descuento 10% + Bonificación 2 gratis

### Bloquear Descuentos (allow_discounts = false)

⚠️ **Regla Importante**: Si CUALQUIER bonificación bloquea descuentos, se bloquean TODOS los descuentos:

- Descuentos de producto
- Descuentos de marca
- Descuentos de proveedor
- Cupones

**Ejemplo**:
- Tienes 3 productos con bonificaciones
- 2 permiten descuentos, 1 bloquea descuentos
- Resultado: Se bloquean TODOS los descuentos

## 🛒 Aplicación en el Carrito

### Cálculo Automático

1. **Al Agregar Producto**
   - Se verifica si el producto tiene bonificaciones activas
   - Se calcula cantidad comprada (sumando todas las líneas)
   - Se calcula bonificaciones aplicables

2. **Al Modificar Cantidad**
   - Se recalcula automáticamente
   - Se actualiza cantidad de bonificaciones

3. **Visualización**
   - Se muestran como productos adicionales
   - Marcados como "Bonificación"
   - Precio: $0.00

### Múltiples Bonificaciones

Un producto puede tener múltiples bonificaciones:

- **Bonificación 1**: Compra 12, Lleva 2
- **Bonificación 2**: Compra 24, Lleva 6

Si compras 24 unidades:
- Se aplican ambas bonificaciones
- Recibes 2 + 6 = 8 bonificaciones

## 📦 Bonificaciones con Variaciones

### Productos con Variaciones

Si el producto tiene variaciones:

1. **Producto que Activa**
   - Puede ser cualquier variación del producto
   - Se suma cantidad total del producto (todas las variaciones)

2. **Producto Bonificación**
   - Puede ser el mismo producto o diferente
   - Si es el mismo producto, puede ser cualquier variación
   - Se puede especificar variación específica

### Ejemplo

- Producto: Camiseta (con variaciones Color y Talla)
- Bonificación: Compra 12 camisetas (cualquier variación), Lleva 2 camisetas (misma variación que compraste)

## 📊 Gestión de Bonificaciones (Administradores)

### Listar Bonificaciones

- Panel Admin → Bonificaciones
- Muestra todas las bonificaciones
- Filtros por producto, estado

### Editar Bonificación

- Cambiar cantidades Compra/Lleva
- Cambiar límite máximo
- Activar/desactivar
- Cambiar configuración de descuentos

### Eliminar Bonificación

⚠️ **Advertencia**: Si hay órdenes con esta bonificación, no se puede eliminar completamente.

- Se marca como inactiva
- Los datos históricos se mantienen

## 📝 Reglas de Negocio

### Cálculo por Unidades Individuales

Las bonificaciones se calculan sobre unidades individuales, considerando `package_quantity`:

- Producto con `package_quantity = 6`
- Compras 2 unidades (que son 12 individuales)
- Bonificación: Compra 12, Lleva 2
- Resultado: Aplica (12 individuales ≥ 12)

### Bonificaciones del Mismo Producto

Si la bonificación es del mismo producto:

- Se agregan como productos adicionales
- Precio: $0.00
- Se muestran en línea separada

### Bonificaciones de Producto Diferente

Si la bonificación es de otro producto:

- Se agrega el otro producto
- Precio: $0.00
- Se muestra en línea separada

### Múltiples Bonificaciones en Misma Orden

- Se pueden aplicar múltiples bonificaciones
- Cada una se calcula independientemente
- Se agregan todas al carrito

## 🔄 Procesamiento en Orden

### Al Crear Orden

1. **Cálculo Final**
   - Se recalcula todas las bonificaciones
   - Se valida que sigan aplicando

2. **Agregar a Orden**
   - Se crean `OrderProduct` para bonificaciones
   - Precio: $0.00
   - Se marca como bonificación

3. **Registro**
   - Se guarda en `order_product_bonifications`
   - Incluye referencia a la bonificación original
   - Útil para auditoría

### Transmisión XML

Las bonificaciones se envían en una orden separada al ERP:

- **Orden Principal**: Productos comprados
- **Orden Bonificación**: Solo productos bonificados
- Campo `TRO_E_obsequio`: 0 para principal, 1 para bonificación

## 📊 Reportes y Análisis

### Bonificaciones Aplicadas

- Panel Admin → Reportes → Bonificaciones
- Muestra bonificaciones aplicadas por período
- Incluye cantidad y valor

### Productos Más Bonificados

- Lista de productos que más generan bonificaciones
- Útil para análisis de promociones

## ⚠️ Consideraciones Importantes

### Cambios en Bonificaciones

- Los cambios afectan nuevas órdenes
- Las órdenes existentes mantienen bonificaciones originales
- Desactivar bonificación no afecta órdenes ya creadas

### Bonificaciones y Inventario

- Las bonificaciones también consumen inventario
- Se valida disponibilidad antes de aplicar
- Se decrementa stock al procesar orden

### Bonificaciones Inactivas

- No se aplican aunque el producto las tenga
- Útil para desactivar temporalmente
- Se pueden reactivar cuando sea necesario

## ❓ Preguntas Frecuentes

### ¿Puedo tener múltiples bonificaciones en el mismo producto?

Sí, un producto puede tener múltiples bonificaciones. Se aplican todas si se cumplen las condiciones.

### ¿Las bonificaciones se acumulan con descuentos?

Depende de la configuración. Si `allow_discounts = true`, sí. Si `allow_discounts = false`, se bloquean todos los descuentos.

### ¿Qué pasa si cambio una bonificación?

Los cambios afectan nuevas órdenes. Las órdenes existentes mantienen las bonificaciones originales.

### ¿Las bonificaciones consumen inventario?

Sí, las bonificaciones también consumen inventario. Se valida disponibilidad antes de aplicar.

### ¿Puedo bonificar un producto diferente?

Sí, puedes configurar que al comprar el Producto A, se bonifique el Producto B.

