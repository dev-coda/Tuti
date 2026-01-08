# Módulo de Descuentos y Promociones

## 📋 Descripción General

El módulo de Descuentos gestiona un sistema jerárquico de descuentos que se aplica automáticamente según reglas de negocio específicas.

## 🎯 Sistema de Descuentos Jerárquico

El sistema aplica descuentos en un orden de prioridad específico. Solo se aplica **el mayor descuento disponible** en cada nivel.

### Niveles de Descuento

1. **Nivel Producto** (Mayor Prioridad)
   - Descuento específico del producto
   - Sobrescribe todos los demás descuentos
   - Configurable por producto

2. **Nivel Marca**
   - Descuento aplicable a todos los productos de una marca
   - Se aplica si el producto no tiene descuento propio
   - Configurable por marca

3. **Nivel Proveedor/Vendedor**
   - Descuento global del proveedor
   - Se aplica si no hay descuento de producto o marca
   - Configurable por proveedor

### Regla de Aplicación

**Solo se aplica el mayor descuento disponible**:

- Si producto tiene 10% y marca tiene 15% → Se aplica 15% (de marca)
- Si producto tiene 20% y marca tiene 15% → Se aplica 20% (de producto)
- Si proveedor tiene 5% y marca tiene 10% → Se aplica 10% (de marca)

## 💰 Tipos de Descuento

### Descuento Porcentual

- Se expresa como porcentaje (ej: 10%, 15%)
- Se calcula sobre el precio base del producto
- Ejemplo: Producto $100 con 10% = $90

### Descuento de Monto Fijo

- Se expresa como monto (ej: $5, $10)
- Se resta directamente del precio
- Ejemplo: Producto $100 con $10 = $90

## 🆕 Descuentos de Primera Compra

### Concepto

Los usuarios que **nunca han realizado una orden** se consideran "primera compra" y pueden acceder a descuentos especiales.

### Configuración

1. **Por Producto**
   - Campo "Descuento Primera Compra"
   - Solo aplica si el usuario no tiene órdenes previas

2. **Por Marca**
   - Descuento de marca para primera compra
   - Se aplica a todos los productos de la marca

3. **Por Proveedor**
   - Descuento global del proveedor para primera compra

### Aplicación

- Se verifica automáticamente si el usuario tiene órdenes previas
- Si no tiene órdenes → Se aplica descuento de primera compra
- Si tiene órdenes → Se aplica descuento normal
- Después de la primera orden, pierde acceso a descuentos de primera compra

## 📊 Descuentos por Volumen

### Concepto

Descuentos que se aplican según la cantidad total comprada de un proveedor en una misma orden.

### Configuración

1. **Panel Admin → Descuentos por Volumen → Crear**

2. **Configuración**
   - **Proveedor**: Proveedor al que aplica
   - **Cantidad Mínima**: Cantidad mínima para activar
   - **Descuento**: Porcentaje o monto fijo
   - **Estado**: Activo/Inactivo

3. **Ejemplo**
   - Proveedor: Coca-Cola
   - Cantidad mínima: 100 unidades
   - Descuento: 5%
   - Si compras 100+ unidades de Coca-Cola → 5% adicional

### Aplicación

- Se calcula la cantidad total por proveedor en la orden
- Si alcanza el mínimo → Se aplica el descuento
- Se suma a otros descuentos aplicables
- Se muestra en el resumen del carrito

## 🔄 Flujo de Aplicación

### Al Agregar Producto al Carrito

1. **Verificar Descuentos Disponibles**
   - Descuento de producto
   - Descuento de marca
   - Descuento de proveedor
   - Descuento de primera compra (si aplica)

2. **Seleccionar Mayor Descuento**
   - Comparar todos los descuentos disponibles
   - Aplicar el mayor

3. **Calcular Precio Final**
   - Precio base - Descuento aplicado
   - Mostrar en carrito

### Al Procesar Orden

1. **Validar Descuentos**
   - Verificar que los descuentos siguen vigentes
   - Verificar que el usuario sigue siendo primera compra (si aplica)

2. **Aplicar Descuentos**
   - Se aplican los mismos descuentos del carrito
   - Se guardan en la orden para historial

3. **Calcular Totales**
   - Subtotal con descuentos
   - Descuentos por volumen (si aplican)
   - Total final

## 📝 Reglas de Negocio

### Prioridad de Descuentos

```
Producto > Marca > Proveedor
```

Siempre se aplica el mayor descuento disponible en el nivel más alto.

### Descuentos Acumulativos

Los descuentos por volumen se **suman** a otros descuentos:

- Descuento de producto: 10%
- Descuento por volumen: 5%
- Total aplicado: 15% (no se multiplican)

### Primera Compra vs Normal

- Si hay descuento de primera compra y normal:
  - Usuario sin órdenes → Se aplica descuento de primera compra
  - Usuario con órdenes → Se aplica descuento normal
- No se pueden aplicar ambos simultáneamente

### Bonificaciones y Descuentos

- Si una bonificación tiene `allow_discounts = false`:
  - Se bloquean TODOS los descuentos
  - Incluye descuentos de producto, marca, proveedor y cupones
- Si `allow_discounts = true`:
  - Los descuentos se aplican normalmente

## ⚙️ Configuración (Administradores)

### Configurar Descuento de Producto

1. **Panel Admin → Productos → Editar**
2. **Sección "Descuentos"**
3. **Campos**:
   - Descuento normal (% o monto)
   - Descuento primera compra (% o monto)
4. **Guardar**

### Configurar Descuento de Marca

1. **Panel Admin → Marcas → Editar**
2. **Sección "Descuentos"**
3. **Campos**:
   - Descuento normal
   - Descuento primera compra
4. **Guardar**

### Configurar Descuento de Proveedor

1. **Panel Admin → Proveedores → Editar**
2. **Sección "Descuentos"**
3. **Campos**:
   - Descuento normal
   - Descuento primera compra
4. **Guardar**

### Configurar Descuento por Volumen

1. **Panel Admin → Descuentos por Volumen → Crear**
2. **Seleccionar Proveedor**
3. **Configurar cantidad mínima y descuento**
4. **Guardar**

## 📊 Visualización

### En Carrito

- Se muestra precio original tachado
- Se muestra precio con descuento
- Se muestra porcentaje o monto de descuento
- Se muestra en resumen de totales

### En Orden

- Se guarda el descuento aplicado
- Se muestra en detalle de orden
- Se incluye en XML de transmisión

## ⚠️ Consideraciones Importantes

### Cambios de Descuento

- Los cambios afectan nuevas órdenes
- Las órdenes existentes mantienen descuentos originales
- Se puede actualizar masivamente desde admin

### Validación de Primera Compra

- Se verifica al crear la orden
- Si el usuario crea una orden mientras tiene otra pendiente, ambas se consideran "primera compra"
- Después de procesar la primera orden, pierde el beneficio

### Descuentos y Cupones

- Los cupones se aplican ADICIONALMENTE a los descuentos
- Se calculan sobre el precio ya descontado
- Ver módulo de Cupones para más detalles

## ❓ Preguntas Frecuentes

### ¿Por qué no se aplica mi descuento?

Verifica:
- Que el descuento esté activo
- Que no haya un descuento mayor en otro nivel
- Que el usuario cumpla condiciones (primera compra, etc.)

### ¿Los descuentos se acumulan?

Los descuentos jerárquicos NO se acumulan (solo el mayor). Los descuentos por volumen SÍ se suman a otros descuentos.

### ¿Puedo tener descuento de primera compra y normal?

Sí, pero solo se aplica uno según si el usuario tiene órdenes previas o no.

### ¿Cómo cambio un descuento?

Edita el producto, marca o proveedor y cambia el descuento. Los cambios afectan nuevas órdenes.

### ¿Los descuentos se aplican a variaciones?

Sí, los descuentos se aplican a productos con variaciones. Se calculan sobre el precio de la variación seleccionada.

