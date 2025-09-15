# Manual de Usuario: Sistema de Cupones y Descuentos

## Tabla de Contenidos
1. [Introducción](#introducción)
2. [Tipos de Descuentos](#tipos-de-descuentos)
3. [Sistema de Descuentos Automáticos](#sistema-de-descuentos-automáticos)
4. [Sistema de Cupones](#sistema-de-cupones)
5. [Gestión de Cupones](#gestión-de-cupones)
6. [Aplicación de Descuentos](#aplicación-de-descuentos)
7. [Casos de Uso Comunes](#casos-de-uso-comunes)
8. [Preguntas Frecuentes](#preguntas-frecuentes)
9. [Documentación Técnica](#documentación-técnica)

---

## Introducción

El sistema de cupones y descuentos de Tuti permite ofrecer promociones flexibles a los clientes a través de dos mecanismos principales:

1. **Descuentos Automáticos**: Aplicados directamente a productos, marcas o proveedores
2. **Cupones**: Códigos promocionales que los clientes pueden ingresar para obtener descuentos específicos

Ambos sistemas trabajan de manera independiente y no se pueden combinar entre sí, garantizando que siempre se aplique el mejor descuento disponible para el cliente.

---

## Tipos de Descuentos

### Descuentos por Porcentaje
- Reducen el precio base del producto en un porcentaje específico
- Ejemplo: 15% de descuento = Precio final: $85.000 (sobre un producto de $100.000)

### Descuentos por Monto Fijo
- Reducen el precio en una cantidad específica de dinero
- Ejemplo: $10.000 de descuento = Precio final: $90.000 (sobre un producto de $100.000)

### Descuentos para Primera Compra
- Algunos descuentos solo se aplican a clientes que no han realizado pedidos anteriormente
- Útil para atraer nuevos clientes

---

## Sistema de Descuentos Automáticos

### Jerarquía de Descuentos

Los descuentos automáticos siguen una jerarquía específica (de mayor a menor prioridad):

1. **Descuentos de Proveedor** (máxima prioridad)
2. **Descuentos de Marca**
3. **Descuentos de Producto** (menor prioridad)

**Importante**: Si existe un descuento de proveedor del 20% y uno de producto del 25%, se aplicará el descuento de proveedor (20%) por tener mayor prioridad.

### Configuración de Descuentos Automáticos

#### Para Productos
1. Ir a la sección de **Productos** en el panel administrativo
2. Seleccionar el producto a modificar
3. En la sección de información básica, configurar:
   - **Descuento**: Porcentaje de descuento (0-100%)
   - **Solo primera compra**: Marcar si el descuento solo aplica para nuevos clientes

#### Para Marcas
1. Acceder a **Marcas** en el panel administrativo
2. Editar la marca deseada
3. Configurar el porcentaje de descuento y las restricciones

#### Para Proveedores
1. Ir a **Proveedores** en el panel administrativo
2. Editar el proveedor
3. Establecer el descuento que se aplicará a todos los productos de ese proveedor

### Reglas Especiales

- **Bonificaciones**: Si un producto tiene bonificaciones activas, todos los descuentos automáticos se desactivan
- **Primera compra**: Los descuentos marcados como "primera compra" solo se muestran a usuarios sin historial de pedidos

---

## Sistema de Cupones

### Características de los Cupones

Los cupones son códigos promocionales que los clientes pueden ingresar durante el proceso de compra. Cada cupón tiene las siguientes características:

#### Información Básica
- **Código**: Texto único que los clientes deben ingresar (ej: "DESCUENTO20")
- **Nombre**: Descripción interna del cupón
- **Descripción**: Explicación detallada de la promoción

#### Configuración de Descuento
- **Tipo**: Monto fijo o porcentaje
- **Valor**: Cantidad del descuento

#### Período de Validez
- **Fecha de inicio**: Cuándo comienza a ser válido
- **Fecha de fin**: Cuándo expira

#### Límites de Uso
- **Límite por cliente**: Cuántas veces puede usar el cupón cada cliente
- **Límite por proveedor**: Restricción específica por proveedor
- **Límite total**: Máximo número de usos del cupón en total

### Reglas de Aplicación de Cupones

#### Aplicación por Objetivo

Los cupones pueden aplicarse a diferentes objetivos:

1. **Carrito completo**: Descuento sobre el total de la compra
2. **Productos específicos**: Solo productos seleccionados
3. **Categorías**: Todos los productos de categorías específicas
4. **Marcas**: Todos los productos de marcas específicas
5. **Proveedores**: Todos los productos de proveedores específicos
6. **Clientes específicos**: Solo ciertos usuarios pueden usarlo
7. **Tipos de cliente**: Solo ciertos roles (ej: vendedores, clientes regulares)

#### Reglas de Exclusión

Los cupones pueden configurarse para NO aplicar a:
- Productos específicos
- Categorías específicas
- Marcas específicas
- Proveedores específicos
- Clientes específicos
- Tipos de cliente específicos

#### Monto Mínimo
- Se puede establecer un monto mínimo de compra para que el cupón sea válido
- Ejemplo: Cupón válido solo para compras superiores a $200.000

---

## Gestión de Cupones

### Crear un Nuevo Cupón

1. **Acceso**: Ir a **Cupones** en el panel administrativo
2. **Información básica**:
   - Ingresar código único (sin espacios, caracteres especiales recomendados: mayúsculas, números, guiones)
   - Agregar nombre descriptivo
   - Escribir descripción detallada

3. **Configurar descuento**:
   - Seleccionar tipo: "Monto fijo" o "Porcentaje"
   - Ingresar valor del descuento

4. **Establecer período**:
   - Fecha y hora de inicio
   - Fecha y hora de finalización

5. **Configurar límites**:
   - Límite por cliente (opcional)
   - Límite total de usos (opcional)
   - Monto mínimo de compra (opcional)

6. **Reglas de aplicación**:
   - Seleccionar a qué se aplica el cupón
   - Configurar excepciones si es necesario

7. **Activar**: Marcar como activo para que esté disponible

### Monitoreo de Cupones

#### Seguimiento de Uso
- **Uso actual**: Cuántas veces se ha utilizado
- **Límite restante**: Cuántos usos quedan disponibles
- **Usuarios que lo han usado**: Lista de clientes que han aplicado el cupón

#### Reportes
- Ingresos generados con cupones
- Cupones más utilizados
- Efectividad de promociones por período

### Desactivar o Modificar Cupones

#### Desactivación Temporal
- Desmarcar "Activo" para pausar el cupón sin eliminarlo
- Útil para promociones estacionales

#### Modificaciones
- Se pueden cambiar las fechas de validez
- Ajustar límites de uso
- Modificar descripciones
- **Importante**: No cambiar el código una vez que los clientes lo conozcan

---

## Aplicación de Descuentos

### Proceso de Aplicación

#### Para Descuentos Automáticos
1. El sistema evalúa automáticamente todos los descuentos disponibles
2. Aplica el descuento de mayor prioridad según la jerarquía
3. Muestra el precio final al cliente
4. Indica el origen del descuento (producto, marca o proveedor)

#### Para Cupones
1. El cliente ingresa el código del cupón en el carrito
2. El sistema valida:
   - Que el cupón existe y está activo
   - Que está dentro del período de validez
   - Que no ha excedido los límites de uso
   - Que el carrito cumple con el monto mínimo
   - Que se aplica a los productos en el carrito
3. Si es válido, calcula y aplica el descuento
4. Muestra el ahorro obtenido

### Precedencia entre Descuentos

**Regla fundamental**: Los cupones tienen prioridad sobre los descuentos automáticos

Si un cliente tiene derecho a un descuento automático del 15% y aplica un cupón del 10%, se aplicará el cupón, anulando el descuento automático.

### Cálculo de Descuentos

#### Descuentos Automáticos
- Se calculan sobre el precio base del producto
- Se aplican antes de impuestos
- Se suman al precio final con impuestos incluidos

#### Cupones de Porcentaje
- Se calculan sobre el subtotal de productos aplicables
- No incluyen impuestos en el cálculo base

#### Cupones de Monto Fijo
- Se descuenta la cantidad exacta especificada
- No puede exceder el valor total del carrito

---

## Casos de Uso Comunes

### Promoción de Lanzamiento
**Objetivo**: Promocionar productos nuevos
**Estrategia**: 
- Crear cupón del 20% para categoría específica
- Período limitado (1-2 semanas)
- Sin límite de uso por cliente

### Descuento por Volumen
**Objetivo**: Incentivar compras grandes
**Estrategia**:
- Cupón de monto fijo ($50.000 de descuento)
- Monto mínimo de compra: $500.000
- Aplicable a carrito completo

### Fidelización de Clientes
**Objetivo**: Retener clientes existentes
**Estrategia**:
- Descuentos automáticos por marca para clientes frecuentes
- Cupones personalizados por tipo de cliente

### Liquidación de Inventario
**Objetivo**: Mover inventario lento
**Estrategia**:
- Descuentos automáticos altos en productos específicos
- Descuentos por marca para marcas con exceso de inventario

### Atracción de Nuevos Clientes
**Objetivo**: Conseguir primeras compras
**Estrategia**:
- Descuentos automáticos marcados como "primera compra"
- Cupones de bienvenida con monto mínimo bajo

---

## Preguntas Frecuentes

### ¿Pueden combinarse descuentos automáticos con cupones?
**No**. El sistema aplicará el descuento más favorable para el cliente, pero nunca ambos al mismo tiempo.

### ¿Qué pasa si un producto tiene bonificación y descuento?
**La bonificación tiene prioridad**. Los descuentos automáticos se desactivan cuando hay bonificaciones activas.

### ¿Puede un cliente usar múltiples cupones en una compra?
**No**. Solo se puede aplicar un cupón por pedido.

### ¿Cómo se manejan los descuentos en productos con variaciones?
Los descuentos se aplican al precio base de cada variación específica.

### ¿Los descuentos se aplican antes o después de impuestos?
Los descuentos se calculan sobre el precio antes de impuestos, pero el precio final mostrado incluye impuestos.

### ¿Qué sucede si un cupón expira mientras el cliente tiene productos en el carrito?
El cupón se invalidará y se mostrará un mensaje de error al intentar proceder con la compra.

### ¿Pueden los vendedores aplicar cupones en nombre de los clientes?
Sí, los vendedores pueden ingresar códigos de cupón durante el proceso de venta.

---

## Documentación Técnica

### Estructura de Base de Datos

#### Tabla: coupons
```sql
- id: Identificador único
- code: Código del cupón (único)
- name: Nombre descriptivo
- description: Descripción detallada
- type: 'fixed_amount' | 'percentage'
- value: Valor del descuento (decimal)
- valid_from: Fecha/hora de inicio (datetime)
- valid_to: Fecha/hora de fin (datetime)
- usage_limit_per_customer: Límite por cliente (nullable)
- usage_limit_per_vendor: Límite por proveedor (nullable)
- total_usage_limit: Límite total (nullable)
- current_usage: Uso actual (integer, default 0)
- applies_to: Enum aplicación
- applies_to_ids: IDs específicos (JSON, nullable)
- except_*_ids: Exclusiones (JSON, nullable)
- minimum_amount: Monto mínimo (decimal, nullable)
- active: Estado activo (boolean)
```

#### Tabla: coupon_usages
```sql
- id: Identificador único
- coupon_id: Referencia al cupón
- user_id: Usuario que usó el cupón
- order_id: Pedido donde se aplicó
- discount_amount: Monto descontado
- used_at: Timestamp de uso
```

### Modelos y Relaciones

#### Modelo Coupon
**Relaciones**:
- `hasMany(CouponUsage)`: Usos del cupón
- `hasMany(Order)`: Pedidos que usaron el cupón

**Métodos principales**:
- `isValid()`: Verifica si está activo y dentro del período
- `hasExceededTotalLimit()`: Verifica límite total
- `hasUserExceededLimit($userId)`: Verifica límite por usuario
- `appliesToProduct($product, $user)`: Verifica aplicabilidad
- `calculateDiscount($cartTotal)`: Calcula monto de descuento
- `incrementUsage()`: Incrementa contador de uso

#### Modelo Product
**Campos de descuento**:
- `discount`: Porcentaje de descuento (0-100)
- `first_purchase_only`: Boolean para restricción de primera compra

**Método principal**:
- `getFinalPriceForUser($hasOrders)`: Calcula precio final considerando descuentos y historial del usuario

### Jerarquía de Descuentos Automáticos

```php
// Orden de prioridad (mayor a menor)
1. Descuento de Proveedor (vendor.discount)
2. Descuento de Marca (brand.discount)  
3. Descuento de Producto (product.discount)

// Condiciones especiales
- Si bonifications.count() > 0: discount = 0
- Si first_purchase_only = true: solo aplica si $hasOrders = false
```

### Validaciones de Cupones

#### CouponService::validateCoupon()
```php
1. Verificar existencia del cupón
2. Verificar estado activo y período válido
3. Verificar límite total de usos
4. Verificar límite por usuario
5. Verificar monto mínimo del carrito
6. Verificar aplicabilidad a productos/usuario
7. Verificar exclusiones
```

#### Aplicación de Cupones
```php
1. Calcular precio base sin promociones existentes
2. Determinar productos aplicables según reglas
3. Calcular descuento según tipo (fixed_amount | percentage)
4. Para monto fijo: min(valor_cupón, total_carrito)
5. Para porcentaje: total_aplicable * (porcentaje / 100)
```

### Configuración y Constantes

#### Tipos de Cupón
```php
TYPE_FIXED_AMOUNT = 'fixed_amount'
TYPE_PERCENTAGE = 'percentage'
```

#### Objetivos de Aplicación
```php
APPLIES_TO_CART = 'cart'
APPLIES_TO_PRODUCT = 'product'
APPLIES_TO_CATEGORY = 'category'
APPLIES_TO_BRAND = 'brand'
APPLIES_TO_VENDOR = 'vendor'
APPLIES_TO_CUSTOMER = 'customer'
APPLIES_TO_CUSTOMER_TYPE = 'customer_type'
```

### Restricciones y Limitaciones

1. **No combinación**: Cupones y descuentos automáticos son mutuamente excluyentes
2. **Un cupón por pedido**: Solo se puede aplicar un código por transacción
3. **Bonificaciones prioritarias**: Anulan todos los descuentos automáticos
4. **Cálculo pre-impuestos**: Los descuentos se calculan antes de agregar IVA
5. **Validación en tiempo real**: Los cupones se validan al momento de aplicar, no al agregar al carrito

### Integraciones

#### Con Sistema de Órdenes
- Los cupones se almacenan en la tabla `orders` (campos: `coupon_id`, `coupon_code`, `coupon_discount`)
- Se crea registro en `coupon_usages` al completar la orden
- Se incrementa `current_usage` del cupón

#### Con Sistema de Inventario
- Los descuentos no afectan la gestión de inventario
- Se calculan sobre precios base, no sobre inventario disponible

#### Con Sistema de Usuarios
- Integración con roles para cupones por tipo de cliente
- Validación de historial de órdenes para descuentos de primera compra
- Seguimiento de uso por usuario para límites personalizados
