# Módulo de Carrito y Órdenes

## 📋 Descripción General

El módulo de Carrito y Órdenes gestiona todo el proceso de compra desde la selección de productos hasta la integración con el sistema ERP externo.

## 🛒 Gestión del Carrito

### Agregar Productos al Carrito

#### Desde la Página de Producto

1. **Selección de Variación** (si aplica)
   - Si el producto tiene variaciones, seleccionar la opción deseada
   - Cada variación puede tener precio diferente

2. **Cantidad**
   - Ingresar la cantidad deseada
   - Respetar el "paso" del producto (ej: solo múltiplos de 6)
   - Verificar disponibilidad de inventario

3. **Botón "Lo Quiero"**
   - Agrega el producto al carrito
   - Muestra notificación de confirmación
   - El carrito se actualiza automáticamente

#### Desde Listados y Carousels

- Los productos destacados y carousels tienen botones "Lo Quiero"
- Funcionan igual que desde la página de producto
- No requiere estar autenticado para agregar al carrito

### Ver el Carrito

1. **Acceso**
   - Icono de carrito en el menú superior
   - Muestra cantidad de items
   - URL: `/cart`

2. **Contenido del Carrito**
   - Lista de productos agregados
   - Cantidad por producto
   - Precio unitario y total por línea
   - Descuentos aplicados
   - Subtotal, descuentos y total general

### Modificar el Carrito

#### Cambiar Cantidad

1. **Botones +/-**
   - Aumentar o disminuir cantidad
   - Se actualiza automáticamente
   - Valida disponibilidad en tiempo real

2. **Campo de Cantidad**
   - Editable directamente
   - Se actualiza al cambiar el foco
   - Valida formato y disponibilidad

#### Eliminar Productos

- Botón "Eliminar" en cada línea del carrito
- Confirmación antes de eliminar
- El carrito se actualiza inmediatamente

### Aplicar Cupón

1. **Campo de Cupón**
   - Ingresar código del cupón
   - Botón "Aplicar"
   - Validación automática

2. **Resultado**
   - Si es válido, se aplica el descuento
   - Se muestra el descuento en el resumen
   - Si es inválido, muestra mensaje de error

3. **Remover Cupón**
   - Botón "Remover" junto al cupón aplicado
   - Restaura el total sin descuento

## 📦 Proceso de Compra

### Paso 1: Revisar Carrito

- Verificar productos y cantidades
- Revisar descuentos aplicados
- Verificar total

### Paso 2: Información del Pedido

#### Selección de Usuario

- Si no estás autenticado, seleccionar usuario existente o crear uno nuevo
- Si estás autenticado, se usa tu usuario automáticamente

#### Selección de Zona

- Seleccionar zona de entrega
- Determina la bodega de inventario
- Afecta fechas de entrega disponibles

#### Dirección de Entrega

- Seleccionar dirección guardada o ingresar nueva
- Validación de campos requeridos
- Se guarda para futuras compras

#### Método de Entrega

1. **Tronex**
   - Entrega programada según ruta
   - Fecha calculada automáticamente
   - Puede tener fecha de transmisión diferida

2. **Express**
   - Entrega rápida
   - Fecha calculada según calendario

#### Observaciones

- Campo opcional para notas especiales
- Se incluye en la orden
- Máximo 500 caracteres

### Paso 3: Validaciones

El sistema valida automáticamente:

1. **Inventario**
   - Disponibilidad en la bodega asignada
   - Stock de seguridad
   - Cantidad solicitada vs disponible

2. **Mínimos de Vendedor**
   - Algunos vendedores tienen mínimos de compra
   - Se valida antes de procesar

3. **Bonificaciones**
   - Se calculan automáticamente
   - Se agregan al carrito si aplican

### Paso 4: Procesamiento

1. **Creación de Orden**
   - Se crea la orden en estado PENDIENTE
   - Se reserva inventario
   - Se aplican descuentos y bonificaciones

2. **Procesamiento Asíncrono**
   - La orden se procesa en segundo plano
   - Integración con sistema ERP vía SOAP
   - Envío de emails de confirmación

3. **Confirmación**
   - Mensaje de éxito
   - Número de orden
   - Redirección a página de inicio

## 📊 Estados de Orden

### Estados Disponibles

1. **Pendiente (0)**
   - Orden creada, esperando procesamiento
   - Color: Amarillo

2. **Procesada (1)**
   - Orden procesada exitosamente
   - Integración con ERP completada
   - Color: Verde

3. **Error (2)**
   - Error en el procesamiento
   - Requiere intervención manual
   - Color: Rojo

4. **Error Webservice (3)**
   - Error en comunicación con ERP
   - Se reintenta automáticamente
   - Color: Rojo

5. **Enviado (4)**
   - Orden enviada al cliente
   - Color: Azul

6. **Entregado (5)**
   - Orden entregada al cliente
   - Color: Verde

7. **Cancelado (6)**
   - Orden cancelada
   - Color: Gris

8. **En Espera (7)**
   - Orden esperando fecha de transmisión
   - Para órdenes Tronex con fecha diferida
   - Color: Púrpura

### Transiciones de Estado

```
PENDIENTE → PROCESADA (éxito)
PENDIENTE → ERROR (fallo)
PENDIENTE → EN ESPERA (fecha diferida)
EN ESPERA → PENDIENTE (fecha llegada)
PROCESADA → ENVIADO → ENTREGADO
Cualquier estado → CANCELADO
```

## 🔄 Procesamiento de Órdenes

### Flujo de Procesamiento

1. **Creación**
   - Usuario completa el checkout
   - Se crea orden con estado PENDIENTE
   - Se reserva inventario

2. **Sincronización de Rutas**
   - Se sincroniza información de rutas del usuario
   - Se actualizan zonas si es necesario
   - Se determina bodega de inventario

3. **Validación de Inventario**
   - Verificación de stock disponible
   - Validación de stock de seguridad
   - Verificación de mínimos de vendedor

4. **Cálculo de Descuentos**
   - Aplicación de descuentos jerárquicos
   - Aplicación de cupones
   - Cálculo de bonificaciones

5. **Transmisión XML**
   - Construcción del XML SOAP
   - Envío al sistema ERP
   - Recepción de respuesta

6. **Actualización de Estado**
   - Si éxito: PROCESADA
   - Si error: ERROR o ERROR_WEBSERVICE
   - Se guarda request y response XML

7. **Envío de Emails**
   - Email de confirmación al cliente
   - Email de estado procesado
   - Emails se envían asíncronamente

### Procesamiento Asíncrono

Las órdenes se procesan usando **Laravel Horizon** y colas Redis:

- **Cola**: `default`
- **Job**: `ProcessOrderAsync`
- **Reintentos**: 3 intentos con backoff (5 min, 30 min, 2 horas)
- **Timeout**: 2 minutos

### Órdenes con Fecha Diferida

Para órdenes Tronex que no coinciden con el día de visita del vendedor:

- Estado: **EN ESPERA**
- `scheduled_transmission_date`: Fecha futura
- El job espera hasta esa fecha
- Se procesa automáticamente cuando llega la fecha

## 📧 Emails de Orden

### Tipos de Email

1. **Confirmación de Orden**
   - Se envía al crear la orden
   - Incluye resumen de productos
   - Número de orden

2. **Estado Procesado**
   - Se envía cuando se procesa exitosamente
   - Confirma integración con ERP

3. **Estado Enviado**
   - Se envía cuando se marca como enviado
   - Información de envío

4. **Estado Entregado**
   - Se envía cuando se marca como entregado
   - Confirmación de entrega

### Configuración de Emails

- Se envían desde cola `emails`
- Configuración en Settings → Email
- Plantillas editables desde admin

## 🔍 Gestión de Órdenes (Administradores)

### Listar Órdenes

1. **Acceso**
   - Panel Admin → Órdenes
   - URL: `/admin/orders`

2. **Filtros Disponibles**
   - Por estado
   - Por usuario
   - Por fecha
   - Por zona
   - Por vendedor

3. **Búsqueda**
   - Por número de orden
   - Por nombre de cliente
   - Por email

### Ver Detalle de Orden

1. **Información General**
   - Número de orden
   - Estado actual
   - Fecha de creación
   - Usuario y zona

2. **Productos**
   - Lista de productos ordenados
   - Cantidades y precios
   - Descuentos aplicados
   - Bonificaciones incluidas

3. **Totales**
   - Subtotal
   - Descuentos
   - Total

4. **Información de Entrega**
   - Dirección
   - Método de entrega
   - Fecha de entrega
   - Observaciones

5. **XML de Transmisión**
   - Request XML enviado
   - Response XML recibido
   - Útil para debugging

### Acciones Disponibles

#### Reintentar XML

- Botón "Reintentar XML"
- Útil si falló la transmisión
- Refresca token y reintenta

#### Reenviar Email

- Reenvía email de confirmación
- Útil si el cliente no recibió el email

#### Cambiar Estado

- Cambiar estado manualmente
- Útil para correcciones

### Retry Automático

Las órdenes con error se reintentan automáticamente:

- **3 intentos** con intervalos crecientes
- Después de 3 intentos, se marca como ERROR_WEBSERVICE
- Se puede reintentar manualmente desde admin

## 📐 Cantidad por Empaque y Paso de Venta

### Cantidad por Empaque (Package Quantity)

Cada producto puede tener una **cantidad por empaque** que afecta cómo se calculan los precios y las bonificaciones.

#### Concepto

- **Cantidad por Empaque**: Número de unidades individuales que contiene un empaque
- **Ejemplo**: Si un producto tiene `package_quantity = 6`, significa que 1 unidad del producto = 6 unidades individuales

#### Efecto en Precios

- El precio mostrado es **por empaque**, no por unidad individual
- Al comprar 1 unidad, estás comprando 1 empaque (6 unidades individuales)
- El total se calcula: `Precio × Cantidad × Package Quantity`

#### Efecto en Bonificaciones

- Las bonificaciones se calculan sobre **unidades individuales**
- Ejemplo:
  - Producto con `package_quantity = 6`
  - Compras 2 unidades (que son 12 individuales)
  - Bonificación: Compra 12, Lleva 2
  - Resultado: Aplica porque 12 individuales ≥ 12

#### Efecto en Inventario

- El inventario se gestiona a nivel de **unidades individuales**
- Al comprar 1 empaque, se decrementan 6 unidades del inventario
- La validación de stock considera unidades individuales

### Paso de Venta (Step)

El **paso de venta** define el múltiplo permitido para comprar un producto.

#### Concepto

- **Paso**: Múltiplo de venta permitido
- **Ejemplo**: Si `step = 6`, solo se puede comprar 6, 12, 18, 24, etc.

#### Validación

- Al agregar producto al carrito, se valida que la cantidad sea múltiplo del paso
- Si ingresas una cantidad que no es múltiplo, se redondea al múltiplo más cercano
- Ejemplo: Si paso es 6 y ingresas 7, se ajusta a 6 o 12 según configuración

#### Configuración

- Se configura por producto en Panel Admin
- Valor por defecto: 1 (sin restricción)
- Útil para productos que solo se venden en cajas o paquetes específicos

## 🔍 Validaciones Avanzadas

### Detección de Órdenes Duplicadas

El sistema detecta y previene órdenes duplicadas dentro de un período de 3 minutos.

#### Proceso

1. **Ventana de Tiempo**: 3 minutos desde la creación de la orden
2. **Criterios de Comparación**:
   - Mismo usuario
   - Misma zona
   - Mismos productos (mismo ID y variación)
   - Mismas cantidades

3. **Acción**:
   - Si se detecta duplicado, se muestra la orden existente
   - Se limpia el carrito
   - Se redirige con mensaje de éxito

#### Propósito

- Previene órdenes accidentales por doble clic
- Evita procesamiento duplicado
- Mejora experiencia del usuario

### Validación de Mínimos de Vendedor

Algunos vendedores tienen un **mínimo de compra** que debe alcanzarse para procesar la orden.

#### Proceso

1. **Cálculo por Vendedor**:
   - Se suman todos los productos del mismo vendedor
   - Se compara con el mínimo configurado

2. **Validación**:
   - Si no se alcanza el mínimo, se muestra alerta
   - La orden no se puede procesar
   - Se muestra mensaje indicando el mínimo requerido

3. **Mensaje de Alerta**:
   - Se muestra en el carrito antes de procesar
   - Indica el vendedor y el mínimo requerido
   - Muestra cuánto falta para alcanzar el mínimo

### Validación de Stock de Seguridad

El sistema valida que el stock disponible esté por encima del stock de seguridad antes de procesar.

#### Reglas

1. **Validación Estricta**:
   - Si `disponible <= stock_de_seguridad`: Se bloquea la venta
   - Mensaje: "Producto está por debajo del stock de seguridad"

2. **Validación de Cantidad Mínima**:
   - Si `disponible <= 5`: Se bloquea la venta
   - Mensaje: "Producto tiene inventario insuficiente"

3. **Validación de Cantidad Solicitada**:
   - Si `cantidad_solicitada > (disponible - reservado)`: Se bloquea
   - Mensaje: "Cantidad solicitada excede inventario disponible"

## 📅 Cálculo de Fechas de Entrega

### Días Hábiles

El sistema calcula fechas de entrega considerando solo días hábiles.

#### Definición de Día Hábil

1. **Días de Semana**: Lunes a Viernes son hábiles por defecto
2. **Sábados**: Pueden ser hábiles si están configurados como tal
3. **Domingos**: Nunca son hábiles
4. **Días Festivos**: Se configuran en Panel Admin → Días Festivos
5. **Sábados Especiales**: Se pueden marcar como hábiles individualmente

#### Cálculo de Próximo Día Hábil

- Se cuenta desde hoy (o mañana si pasó la hora de cierre)
- Se saltan domingos, festivos y sábados no hábiles
- Se encuentra el próximo día hábil disponible

### Hora de Cierre

La **hora de cierre** determina si una orden se procesa para el mismo día o para el siguiente.

#### Funcionamiento

1. **Configuración**: Panel Admin → Configuración → Hora de Cierre
2. **Lógica**:
   - Si la hora actual es **antes** de la hora de cierre → Se cuenta desde hoy
   - Si la hora actual es **después** de la hora de cierre → Se cuenta desde mañana

3. **Ejemplo**:
   - Hora de cierre: 14:00 (2 PM)
   - Orden a las 13:00 → Se cuenta desde hoy
   - Orden a las 15:00 → Se cuenta desde mañana

### Método Tronex - Cálculo de Fecha

Para órdenes Tronex, la fecha se calcula basándose en:

1. **Ruta del Usuario**: Se obtiene de la zona seleccionada
2. **Ciclo de Ruta**: Se mapea la ruta a un ciclo (A, B, C)
3. **Día de Entrega**: Se obtiene del campo "day" de la zona (ej: "5-Viernes")
4. **Calendario de Entrega**: Se busca la próxima semana disponible para ese ciclo
5. **Día de Visita del Vendedor**: Se encuentra el día de la semana dentro de esa semana
6. **Fecha de Entrega**: Día de visita + 1 día hábil

#### Ejemplo Completo

- Usuario con zona que tiene ruta "RUTA-01" y día "5-Viernes"
- Ruta "RUTA-01" pertenece al ciclo "A"
- Se busca próxima semana disponible para ciclo "A"
- Se encuentra viernes dentro de esa semana
- Fecha de entrega = viernes + 1 día hábil = lunes siguiente

### Método Express - Cálculo de Fecha

Para órdenes Express, la fecha se calcula más simplemente:

1. **Días Hábiles Adelante**: Configurable (normalmente 1-2 días)
2. **Cálculo**: Hoy (o mañana si pasó hora de cierre) + N días hábiles
3. **Ejemplo**: Si son 2 días hábiles y ordenas el lunes → Entrega el miércoles

## 💰 Cálculo de Impuestos

### Impuestos por Producto

Cada producto puede tener un impuesto configurado que se aplica al precio.

#### Proceso de Cálculo

1. **Precio Base**: Precio del producto (o variación)
2. **Descuentos**: Se aplican descuentos primero
3. **Precio con Descuento**: Precio base - descuentos
4. **Impuesto**: Se calcula sobre precio con descuento
5. **Precio Final**: Precio con descuento + impuesto

#### Fórmula

```
Precio con Descuento = Precio Base × (1 - Descuento%)
Precio Final = Precio con Descuento × (1 + Impuesto%)
```

#### Ejemplo

- Precio base: $100,000
- Descuento: 10% → $90,000
- Impuesto: 19% → $90,000 × 1.19 = $107,100

### Impuestos y Cantidad por Empaque

- El impuesto se calcula sobre el precio **por empaque**
- Luego se multiplica por la cantidad de empaques
- Ejemplo: Precio con impuesto $107,100 × 2 empaques = $214,200

## 📊 Reglas de Negocio

### Inventario

1. **Reserva de Inventario**
   - Se reserva al crear la orden
   - Se libera si se cancela
   - Se decrementa al procesar

2. **Validación de Stock**
   - Verifica disponibilidad antes de procesar
   - Considera stock de seguridad
   - Valida cantidad solicitada

3. **Productos con Variaciones**
   - El inventario es compartido entre variaciones
   - Se decrementa del producto padre
   - Todas las variaciones comparten el mismo stock

### Descuentos

1. **Jerarquía de Descuentos**
   - Descuento de producto (mayor prioridad)
   - Descuento de marca
   - Descuento de vendedor
   - Se aplica el mayor descuento

2. **Primera Compra**
   - Descuentos especiales para primera compra
   - Se pierde después de la primera orden
   - Se verifica automáticamente

3. **Cupones**
   - Se aplican sobre productos elegibles
   - Pueden tener exclusiones
   - Se validan límites de uso

### Bonificaciones

1. **Cálculo Automático**
   - Se calculan al agregar productos al carrito
   - Se agregan automáticamente
   - Se muestran como "Bonificación"

2. **Límites**
   - Pueden tener límite máximo por orden
   - Se respeta el límite configurado

3. **Bloqueo de Descuentos**
   - Si una bonificación bloquea descuentos, se desactivan todos los descuentos
   - Incluye cupones y descuentos tradicionales

### Mínimos de Vendedor

- Algunos vendedores tienen mínimos de compra
- Se valida antes de procesar
- Muestra mensaje si no se alcanza el mínimo

## ⚠️ Errores Comunes

### "Inventario Insuficiente"

- El producto no tiene suficiente stock
- Verificar disponibilidad antes de ordenar
- Contactar administrador si persiste

### "No se pudo determinar la bodega"

- El usuario no tiene zona asignada
- Sincronizar rutas del usuario
- Asignar zona manualmente

### "Error en transmisión XML"

- Error de comunicación con ERP
- Se reintenta automáticamente
- Contactar administrador si persiste después de 3 intentos

### "La zona del pedido no existe"

- La zona fue eliminada después de crear la orden
- Se marca como error
- Requiere intervención manual

## 📝 Notas Técnicas

- Las órdenes se almacenan en tabla `orders`
- Los productos de orden en `order_products`
- Las bonificaciones en `order_product_bonifications`
- El procesamiento usa jobs asíncronos con Horizon
- La integración con ERP es vía SOAP XML
- Los emails se envían desde cola `emails`

## ❓ Preguntas Frecuentes

### ¿Puedo modificar una orden después de crearla?

No, las órdenes no se pueden modificar después de crearse. Si necesitas cambios, cancela la orden y crea una nueva.

### ¿Qué pasa si un producto se agota después de agregarlo al carrito?

El sistema valida el inventario antes de procesar. Si no hay suficiente stock, mostrará un error y no procesará la orden.

### ¿Puedo cancelar una orden?

Solo los administradores pueden cancelar órdenes. Contacta al administrador si necesitas cancelar.

### ¿Cuánto tiempo tarda en procesarse una orden?

Normalmente se procesa en segundos. Si hay problemas de comunicación con el ERP, puede tomar hasta 2 horas (con reintentos automáticos).

### ¿Recibiré confirmación por email?

Sí, recibirás un email de confirmación al crear la orden y otro cuando se procese exitosamente.

