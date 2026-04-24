# Módulo de Catálogo de Productos

## 📋 Descripción General

El módulo de Catálogo gestiona todos los aspectos relacionados con los productos: creación, edición, variaciones, categorías, marcas y organización del catálogo.

## 🏷️ Tipos de Productos

### Productos Simples

Productos con un solo SKU y precio fijo:

- **Características**:
  - Un solo precio
  - Un solo SKU
  - Sin variaciones
  - Gestión de inventario directa

- **Uso**: Productos estándar sin opciones

### Productos con Variaciones

Productos que tienen múltiples opciones (color, tamaño, etc.):

- **Estructura**:
  ```
  Producto (Padre)
  └── Variación (Tipo: Color, Tamaño, etc.)
      └── Items de Variación (Rojo, Azul, Grande, Pequeño, etc.)
  ```

- **Características**:
  - Múltiples items con precios independientes
  - SKU único por item
  - Inventario compartido (a nivel de producto padre)
  - Selección obligatoria al comprar

- **Ejemplo**: Camiseta con variaciones de Color y Talla

### Productos Combinados

Productos que agrupan múltiples productos simples:

- **Características**:
  - Precio conjunto
  - Puede tener descuento sobre productos individuales
  - Se muestra como un solo producto
  - Al comprar, se agregan todos los productos incluidos

- **Uso**: Paquetes promocionales, combos

## 📦 Gestión de Productos

### Crear Producto

1. **Acceso**
   - Panel Admin → Productos → Crear Producto
   - URL: `/admin/products/create`

2. **Información Básica**
   - **Nombre**: Nombre del producto (requerido)
   - **Slug**: URL amigable (se genera automáticamente)
   - **Descripción**: Descripción detallada
   - **SKU**: Código único del producto
   - **Precio**: Precio base del producto
   - **Categoría**: Categoría principal (requerido)
   - **Marca**: Marca del producto (requerido)
   - **Proveedor**: Proveedor/Vendedor (requerido)

3. **Configuración de Inventario**
   - **Gestión de Inventario**: Activar/desactivar
   - **Stock de Seguridad**: Cantidad mínima antes de alertar
   - **Cantidad por Empaque**: Unidades por empaque (default: 1)
   - **Paso**: Múltiplo de venta (ej: solo múltiplos de 6)

4. **Imágenes**
   - Subir imagen principal
   - Subir imágenes adicionales
   - Formato: JPG, PNG
   - Tamaño recomendado: 800x800px

5. **Etiquetas y Destacados**
   - Seleccionar etiquetas
   - Marcar como destacado
   - Destacar por categoría

6. **Estado**
   - **Activo**: Visible en el catálogo
   - **Inactivo**: Oculto pero no eliminado

### Editar Producto

1. **Acceso**
   - Panel Admin → Productos → Lista → Editar

2. **Campos Editables**
   - Todos los campos de creación
   - Precio (se actualiza en variaciones si aplica)
   - Estado activo/inactivo

3. **Restricciones**
   - No se puede cambiar el SKU si hay órdenes
   - Cambiar categoría puede afectar destacados

### Eliminar Producto

⚠️ **Advertencia**: Eliminar un producto es una acción irreversible.

- Si el producto tiene órdenes, no se puede eliminar
- Se marca como inactivo en lugar de eliminar
- Los datos históricos se mantienen

## 🔄 Variaciones de Producto

### Crear Variación

1. **Acceso**
   - Panel Admin → Productos → Editar → Pestaña "Variaciones"

2. **Tipo de Variación**
   - Crear tipo de variación (Color, Talla, etc.)
   - O seleccionar tipo existente

3. **Items de Variación**
   - Agregar items (Rojo, Azul, Grande, Pequeño, etc.)
   - Cada item puede tener:
     - **Nombre**: Nombre del item
     - **SKU**: SKU único (opcional)
     - **Precio**: Precio específico (opcional, usa precio base si no se especifica)
     - **Estado**: Activo/Inactivo

4. **Guardar**
   - Se guardan todas las variaciones
   - El producto ahora requiere selección de variación

### Gestionar Items de Variación

- **Editar**: Cambiar precio, SKU, estado
- **Eliminar**: Remover item (solo si no hay órdenes)
- **Ordenar**: Arrastrar para cambiar orden de visualización

### Inventario de Variaciones

- El inventario se gestiona a nivel de producto padre
- Todas las variaciones comparten el mismo stock
- Al comprar cualquier variación, se decrementa del stock del producto padre

## 📁 Categorías

### Crear Categoría

1. **Acceso**
   - Panel Admin → Categorías → Crear Categoría

2. **Información**
   - **Nombre**: Nombre de la categoría
   - **Slug**: URL amigable
   - **Descripción**: Descripción de la categoría
   - **Categoría Padre**: Para crear subcategorías (opcional)
   - **Imagen**: Imagen representativa
   - **Orden**: Orden de visualización

3. **Jerarquía**
   - Las categorías pueden tener subcategorías
   - Máximo 3 niveles recomendado
   - Se muestra en navegación jerárquica

### Organizar Categorías

- **Árbol de Categorías**: Vista jerárquica
- **Arrastrar y Soltar**: Para reorganizar
- **Subcategorías**: Crear dentro de categorías existentes

### Destacar Categorías

- Marcar categorías como destacadas
- Aparecen en página principal
- Se pueden ordenar por prioridad

## 🏢 Marcas

### Crear Marca

1. **Acceso**
   - Panel Admin → Marcas → Crear Marca

2. **Información**
   - **Nombre**: Nombre de la marca
   - **Slug**: URL amigable
   - **Descripción**: Descripción de la marca
   - **Proveedor**: Proveedor asociado
   - **Logo**: Logo de la marca
   - **Estado**: Activo/Inactivo

### Asignar Marca a Productos

- Al crear/editar producto, seleccionar marca
- Los productos heredan descuentos de marca
- Se pueden filtrar productos por marca

## 🏭 Proveedores/Vendedores

### Crear Proveedor

1. **Acceso**
   - Panel Admin → Proveedores → Crear Proveedor

2. **Información**
   - **Nombre**: Nombre del proveedor
   - **Código**: Código único
   - **Email**: Email de contacto
   - **Teléfono**: Teléfono de contacto
   - **Estado**: Activo/Inactivo

3. **Configuración**
   - **Mínimo de Compra**: Mínimo requerido para ordenar
   - **Descuentos**: Configurar descuentos del proveedor

### Asignar Proveedor a Productos

- Cada producto debe tener un proveedor
- Los productos heredan descuentos del proveedor
- Se pueden filtrar productos por proveedor

## 🏷️ Etiquetas

### Crear Etiqueta

1. **Acceso**
   - Panel Admin → Etiquetas → Crear Etiqueta

2. **Información**
   - **Nombre**: Nombre de la etiqueta
   - **Color**: Color de visualización
   - **Tipo**: Tipo de etiqueta (Nuevo, Oferta, etc.)

### Asignar Etiquetas a Productos

- En edición de producto, seleccionar etiquetas
- Múltiples etiquetas por producto
- Se muestran en tarjetas de producto

## ⭐ Productos Destacados

### Destacar Producto Globalmente

1. **Acceso**
   - Panel Admin → Productos Destacados

2. **Agregar Producto**
   - Seleccionar producto
   - Ordenar por prioridad
   - Aparece en página principal

### Destacar Producto por Categoría

1. **Acceso**
   - Panel Admin → Categorías → Editar → Destacados

2. **Agregar Productos**
   - Seleccionar productos de esa categoría
   - Ordenar por prioridad
   - Aparece en página de categoría

## 📊 Reglas de Negocio

### Precios

1. **Precio Base**
   - Precio estándar del producto
   - Se usa si no hay variaciones con precio específico

2. **Precios de Variación**
   - Pueden tener precio diferente al base
   - Si no se especifica, usa precio base

3. **Descuentos**
   - Se aplican sobre precio base o variación
   - Jerarquía: Producto > Marca > Proveedor

### SKUs

1. **SKU de Producto**
   - Requerido para productos simples
   - Opcional para productos con variaciones

2. **SKU de Variación**
   - Opcional por item de variación
   - Si no se especifica, se genera automáticamente

3. **Unicidad**
   - Los SKUs deben ser únicos
   - No se puede cambiar si hay órdenes

### Cantidad por Empaque

- Define cuántas unidades tiene un empaque
- Afecta el cálculo de precios
- Ejemplo: Si es 6, al comprar 1 se compran 6 unidades

### Paso de Venta

- Define el múltiplo de venta permitido
- Ejemplo: Si es 6, solo se puede comprar 6, 12, 18, etc.
- Se valida al agregar al carrito

### Gestión de Inventario

1. **Productos con Inventario**
   - Se gestiona stock por bodega
   - Se valida disponibilidad al comprar
   - Se reserva al crear orden

2. **Productos sin Inventario**
   - No se valida stock
   - Siempre disponibles
   - Útil para servicios o productos especiales

## 🔍 Búsqueda y Filtros

### Búsqueda de Productos

- **Por Nombre**: Búsqueda textual
- **Por SKU**: Búsqueda por código
- **Por Categoría**: Filtrar por categoría
- **Por Marca**: Filtrar por marca
- **Por Proveedor**: Filtrar por proveedor

### Filtros Avanzados

- **Precio**: Rango de precios
- **Estado**: Activo/Inactivo
- **Con Inventario**: Solo productos con gestión de inventario
- **Destacados**: Solo productos destacados

## 📝 Importación Masiva

### Importar Productos desde Excel

1. **Preparar Archivo**
   - Formato Excel (.xlsx)
   - Columnas requeridas: nombre, precio, SKU, categoría, marca

2. **Importar**
   - Panel Admin → Productos → Importar
   - Seleccionar archivo
   - Mapear columnas
   - Validar y confirmar

3. **Resultado**
   - Se muestran productos creados
   - Errores si los hay
   - Log de importación

## ⚠️ Consideraciones Importantes

### Al Eliminar

- No se pueden eliminar productos con órdenes
- No se pueden eliminar categorías con productos
- No se pueden eliminar marcas con productos

### Al Cambiar Precios

- Los cambios afectan nuevas órdenes
- Las órdenes existentes mantienen precio original
- Se puede actualizar masivamente desde admin

### Al Cambiar SKU

- No se puede cambiar si hay órdenes
- Afecta sincronización con sistemas externos
- Requiere actualizar referencias externas

## 📊 Reportes

### Productos Más Vendidos

- Lista de productos ordenados por ventas
- Útil para identificar productos populares
- Se actualiza automáticamente

### Productos con Bajo Stock

- Lista de productos bajo stock de seguridad
- Alerta para reposición
- Filtrable por bodega

## ❓ Preguntas Frecuentes

### ¿Puedo tener productos sin precio?

No, todos los productos deben tener precio. Si es una variación, puede usar el precio base del producto.

### ¿Cómo funcionan los productos combinados?

Los productos combinados agrupan múltiples productos. Al comprar uno, se agregan todos los productos incluidos al carrito.

### ¿Puedo cambiar el SKU de un producto?

Solo si no tiene órdenes asociadas. Si tiene órdenes, contacta al administrador.

### ¿Qué pasa si elimino una categoría con productos?

No se puede eliminar. Primero debes mover o eliminar los productos de esa categoría.

### ¿Cómo actualizo precios masivamente?

Usa la función de importación masiva o contacta al administrador para actualización por lotes.

