# Módulo de Cupones

## 📋 Descripción General

El módulo de Cupones permite crear códigos promocionales que los clientes pueden aplicar en sus compras para obtener descuentos adicionales.

## 🎫 Concepto de Cupón

Un cupón es un código que el cliente ingresa en el carrito para obtener un descuento adicional sobre los productos elegibles.

### Características

- **Código único**: Cada cupón tiene un código único
- **Tipos de descuento**: Porcentaje o monto fijo
- **Criterios flexibles**: Se pueden configurar múltiples condiciones
- **Límites de uso**: Por usuario, por orden, global
- **Exclusiones**: Productos, categorías o marcas excluidas

## ➕ Crear Cupón

### Paso 1: Información Básica

1. **Panel Admin → Cupones → Crear Cupón**

2. **Datos Requeridos**:
   - **Código**: Código único del cupón (ej: VERANO2025)
   - **Tipo**: Porcentaje o Monto Fijo
   - **Valor**: Porcentaje (ej: 10) o Monto (ej: 5000)
   - **Descripción**: Descripción del cupón (opcional)

### Paso 2: Configuración de Validez

1. **Fechas**
   - **Fecha Inicio**: Cuándo empieza a ser válido
   - **Fecha Fin**: Cuándo deja de ser válido
   - Si no se especifica, no expira

2. **Estado**
   - **Activo**: El cupón está vigente
   - **Inactivo**: No se puede usar

### Paso 3: Criterios de Aplicación

1. **Mínimo de Compra**
   - Monto mínimo del carrito para aplicar
   - Ejemplo: $50,000 mínimo

2. **Primera Compra**
   - **Solo primera compra**: Solo usuarios sin órdenes previas
   - **Cualquier compra**: Todos los usuarios

3. **Productos Elegibles**
   - **Todos los productos**: Aplica a todo el carrito
   - **Productos específicos**: Solo productos seleccionados
   - **Categorías específicas**: Solo productos de categorías seleccionadas
   - **Marcas específicas**: Solo productos de marcas seleccionadas

4. **Exclusiones**
   - **Productos excluidos**: Productos que NO aplican
   - **Categorías excluidas**: Categorías que NO aplican
   - **Marcas excluidas**: Marcas que NO aplican

### Paso 4: Límites de Uso

1. **Límite por Usuario**
   - Cuántas veces puede usar el mismo usuario
   - Ejemplo: 1 vez por usuario

2. **Límite por Orden**
   - Si se puede usar múltiples veces en la misma orden
   - Normalmente: 1 vez por orden

3. **Límite Global**
   - Cantidad total de usos permitidos
   - Ejemplo: 100 usos totales

### Paso 5: Guardar

- Se guarda el cupón
- Está listo para ser usado

## 💰 Tipos de Descuento

### Cupón Porcentual

- Se expresa como porcentaje (ej: 10%, 15%)
- Se calcula sobre el subtotal de productos elegibles
- **Ejemplo**:
  - Productos elegibles: $100,000
  - Cupón: 10%
  - Descuento: $10,000
  - Total: $90,000

### Cupón de Monto Fijo

- Se expresa como monto (ej: $5,000, $10,000)
- Se resta directamente del total
- **Ejemplo**:
  - Total carrito: $100,000
  - Cupón: $10,000
  - Total: $90,000

### Distribución Proporcional

Para cupones de monto fijo, el descuento se distribuye proporcionalmente entre productos elegibles:

- Producto A: $60,000 (60% del total)
- Producto B: $40,000 (40% del total)
- Cupón: $10,000
- Descuento A: $6,000 (60% de $10,000)
- Descuento B: $4,000 (40% de $10,000)

## 🔄 Aplicación de Cupón

### Proceso de Aplicación

1. **Cliente ingresa código**
   - En el carrito, campo "Código de cupón"
   - Click en "Aplicar"

2. **Validación**
   - Verifica que el código existe
   - Verifica que está activo
   - Verifica fechas de validez
   - Verifica límites de uso
   - Verifica mínimo de compra
   - Verifica criterios de aplicación

3. **Cálculo de Descuento**
   - Identifica productos elegibles
   - Calcula descuento según tipo
   - Distribuye si es monto fijo

4. **Aplicación**
   - Se muestra descuento en carrito
   - Se actualiza total
   - Se guarda en sesión

### Validaciones

#### Validación de Código

- El código debe existir
- Debe estar activo
- No debe haber expirado

#### Validación de Usuario

- Si es "solo primera compra", verifica que el usuario no tenga órdenes
- Verifica límite de uso por usuario

#### Validación de Carrito

- Verifica mínimo de compra
- Verifica que hay productos elegibles
- Verifica límite de uso por orden

#### Validación de Productos

- Identifica productos elegibles según criterios
- Excluye productos/categorías/marcas excluidas
- Si no hay productos elegibles, el cupón no aplica

## 📊 Reglas de Aplicación

### Productos Elegibles

1. **Todos los productos**
   - Aplica a todo el carrito
   - Excepto exclusiones

2. **Productos específicos**
   - Solo productos seleccionados
   - Otros productos no aplican

3. **Categorías específicas**
   - Solo productos de categorías seleccionadas
   - Otros productos no aplican

4. **Marcas específicas**
   - Solo productos de marcas seleccionadas
   - Otros productos no aplican

### Exclusiones

Las exclusiones tienen prioridad sobre las inclusiones:

- Si un producto está en productos elegibles PERO también en exclusiones → NO aplica
- Si una categoría está en categorías elegibles PERO también en exclusiones → NO aplica

### Cálculo de Descuento

#### Cupón Porcentual

```
Descuento = Suma(Precio de productos elegibles) * Porcentaje / 100
```

#### Cupón Monto Fijo

```
Descuento Total = Valor del cupón
Descuento por Producto = (Precio del producto / Total productos elegibles) * Descuento Total
```

### Límites de Uso

1. **Por Usuario**
   - Se cuenta cuántas veces ha usado el usuario
   - Si alcanza el límite, no puede usar más

2. **Por Orden**
   - Normalmente 1 vez por orden
   - Evita usar múltiples veces en la misma compra

3. **Global**
   - Se cuenta total de usos
   - Si alcanza el límite, nadie puede usar más

## 💡 Ejemplos de Configuración

### Ejemplo 1: Cupón de Bienvenida

- **Código**: BIENVENIDA10
- **Tipo**: Porcentaje
- **Valor**: 10%
- **Primera compra**: Solo primera compra
- **Mínimo**: $30,000
- **Límite por usuario**: 1
- **Aplica a**: Todos los productos

### Ejemplo 2: Cupón de Marca Específica

- **Código**: COCACOLA5000
- **Tipo**: Monto fijo
- **Valor**: $5,000
- **Mínimo**: $50,000
- **Marca elegible**: Coca-Cola
- **Límite global**: 100 usos

### Ejemplo 3: Cupón con Exclusiones

- **Código**: TODO20
- **Tipo**: Porcentaje
- **Valor**: 20%
- **Aplica a**: Todos los productos
- **Excluye**: Categoría "Bebidas Alcohólicas"
- **Resultado**: 20% en todo excepto bebidas alcohólicas

## 🔍 Gestión de Cupones (Administradores)

### Listar Cupones

- Panel Admin → Cupones
- Muestra todos los cupones
- Filtros por estado, tipo, fecha

### Ver Detalle de Cupón

- Información completa del cupón
- Estadísticas de uso
- Lista de órdenes que lo usaron

### Editar Cupón

- Cambiar configuración
- Activar/desactivar
- Modificar límites

⚠️ **Nota**: Cambiar un cupón no afecta órdenes ya creadas.

### Eliminar Cupón

- Si tiene usos, no se puede eliminar completamente
- Se marca como inactivo
- Los datos históricos se mantienen

## 📊 Reportes y Estadísticas

### Uso de Cupones

- Panel Admin → Cupones → Ver Cupón
- Muestra:
  - Total de usos
  - Total de descuento aplicado
  - Lista de órdenes que lo usaron
  - Usuarios que lo usaron

### Cupones Más Usados

- Lista de cupones ordenados por uso
- Útil para identificar promociones exitosas

## 🔄 Integración con Descuentos

### Cupones y Descuentos Tradicionales

Los cupones se aplican **ADICIONALMENTE** a los descuentos tradicionales:

1. Se aplican descuentos de producto/marca/proveedor
2. Se calcula subtotal con descuentos
3. Se aplica cupón sobre el subtotal descontado

**Ejemplo**:
- Producto: $100,000
- Descuento producto: 10% → $90,000
- Cupón: 10% sobre $90,000 → $9,000
- Total: $81,000

### Cupones y Bonificaciones

Si una bonificación tiene `allow_discounts = false`:
- Se bloquean TODOS los descuentos
- **Incluye cupones**
- El cupón no se puede aplicar

## ⚠️ Consideraciones Importantes

### Cambios en Cupones

- Los cambios afectan nuevas aplicaciones
- Las órdenes existentes mantienen el cupón original
- Desactivar cupón no afecta órdenes ya creadas

### Expiración de Cupones

- Los cupones expirados no se pueden usar
- Se valida automáticamente al aplicar
- Se puede extender fecha de expiración

### Límites de Uso

- Una vez alcanzado el límite, el cupón no se puede usar más
- Se puede aumentar el límite si es necesario
- Los límites se verifican al aplicar

## ❓ Preguntas Frecuentes

### ¿Puedo usar múltiples cupones en una orden?

No, solo se puede usar un cupón por orden.

### ¿Qué pasa si un cupón expira mientras tengo productos en el carrito?

El cupón se valida al aplicar. Si expira después de aplicarlo, sigue válido para esa orden.

### ¿Los cupones se pueden combinar con descuentos?

Sí, los cupones se aplican adicionalmente a los descuentos tradicionales.

### ¿Puedo crear un cupón que solo aplique a ciertos productos?

Sí, puedes configurar productos, categorías o marcas específicas en "Productos Elegibles".

### ¿Cómo veo quién usó un cupón?

En el detalle del cupón, sección "Estadísticas de Uso", verás la lista de órdenes y usuarios que lo usaron.

