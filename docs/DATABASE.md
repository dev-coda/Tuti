# Documentación de Base de Datos - Sistema Tuti

## 📊 Visión General

La base de datos del sistema Tuti está diseñada para soportar un ecosistema complejo de comercio B2B con las siguientes características principales:

-   **Gestión de productos multi-variación**
-   **Sistema de descuentos jerárquico**
-   **Inventario por múltiples bodegas**
-   **Bonificaciones automatizadas**
-   **Gestión geográfica de usuarios**
-   **Sistema de cupones avanzado**

## 🏛️ Entidades Principales

### 👤 Gestión de Usuarios

#### **users**

Tabla central de usuarios del sistema que incluye compradores (tenderos), vendedores y administradores.

**Propósito**: Almacenar información completa de usuarios con datos personales, empresariales y geográficos.

**Campos Principales**:

-   `name`, `email`, `password` - Datos básicos de autenticación
-   `document`, `phone` - Información de contacto
-   `company`, `address` - Datos empresariales
-   `state_id`, `city_id` - Ubicación geográfica
-   `zone` - Zona de entrega asignada
-   `status_id` - Estado del usuario (PENDING=1, ACTIVE=2)
-   `terms_accepted` - Aceptación de términos y condiciones

**Relaciones**:

-   Pertenece a una ciudad (`cities`) y estado (`states`)
-   Tiene múltiples órdenes (`orders`)
-   Puede tener zonas asignadas (`zones`)

#### **states** y **cities**

Estructura geográfica para ubicación de usuarios.

**Propósito**: Organizar la distribución geográfica para logística y asignación de vendedores.

#### **zones**

Zonas de entrega con rutas específicas.

**Propósito**: Definir rutas de entrega, días de visita y asignación de vendedores por zona.

**Campos**:

-   `route`, `zone`, `day` - Información logística
-   `address`, `code` - Identificación de zona
-   `user_id` - Vendedor asignado

### 🏭 Gestión de Proveedores

#### **vendors**

Proveedores principales del sistema.

**Propósito**: Representar las empresas proveedoras que suministran productos a través de múltiples marcas.

**Campos**:

-   `name`, `slug` - Identificación
-   `image`, `banner` - Elementos visuales
-   `minimum_purchase` - Compra mínima requerida
-   `discount`, `first_purchase_only` - Descuentos a nivel proveedor
-   `vendor_type` - Tipo de proveedor

**Relaciones**:

-   Tiene múltiples marcas (`brands`)

#### **brands**

Marcas de productos asociadas a proveedores.

**Propósito**: Agrupar productos bajo marcas específicas con políticas de descuento independientes.

**Campos**:

-   `name`, `slug`, `description` - Identificación y descripción
-   `image`, `banner` - Elementos visuales
-   `delivery_days` - Días de entrega específicos
-   `discount`, `first_purchase_only` - Política de descuentos
-   `vendor_id` - Proveedor asociado

**Relaciones**:

-   Pertenece a un proveedor (`vendors`)
-   Tiene múltiples productos (`products`)

### 📦 Gestión de Productos

#### **products**

Entidad central del catálogo de productos.

**Propósito**: Almacenar información completa de productos incluyendo precios, inventario, especificaciones y configuraciones comerciales.

**Campos Principales**:

-   `name`, `description`, `short_description` - Información descriptiva
-   `technical_specifications`, `warranty`, `other_information` - Detalles técnicos
-   `sku`, `slug` - Identificadores únicos
-   `price` - Precio base
-   `discount`, `first_purchase_only` - Descuentos específicos
-   `quantity_min`, `quantity_max`, `step` - Restricciones de cantidad
-   `delivery_days` - Días de entrega
-   `package_quantity` - Cantidad por empaque
-   `safety_stock` - Stock de seguridad
-   `inventory_opt_out` - Exclusión de gestión de inventario
-   `sales_count` - Contador de ventas para ranking
-   `is_combined` - Indicador de producto combinado
-   `parent_id` - Relación padre-hijo para empaques

**Relaciones**:

-   Pertenece a una marca (`brands`)
-   Pertenece a un impuesto (`taxes`)
-   Pertenece a múltiples categorías (`categories` - many-to-many)
-   Tiene múltiples etiquetas (`labels` - many-to-many)
-   Tiene múltiples imágenes (`product_images`)
-   Tiene inventario en múltiples bodegas (`product_inventories`)
-   Puede tener variaciones (`variations` a través de `variation_items`)
-   Puede tener productos relacionados (many-to-many consigo mismo)
-   Puede estar en múltiples bonificaciones (`bonifications`)

#### **categories**

Categorización jerárquica de productos.

**Propósito**: Organizar productos en una estructura de categorías y subcategorías con funcionalidades de destacado.

**Campos**:

-   `name`, `slug`, `description` - Identificación
-   `image` - Imagen representativa
-   `parent_id` - Categoría padre (estructura jerárquica)
-   `default_sort_order` - Orden predeterminado de productos
-   `enable_highlighting` - Habilitar productos destacados
-   `highlighted_brand_ids` - Marcas destacadas en la categoría
-   `inventory_opt_out` - Exclusión de gestión de inventario
-   `safety_stock` - Stock de seguridad por defecto

**Relaciones**:

-   Puede tener categoría padre (`categories`)
-   Puede tener subcategorías (`categories`)
-   Tiene múltiples productos (`products` - many-to-many)
-   Puede tener productos destacados (`product_highlights`)

#### **labels**

Etiquetas para clasificación adicional de productos.

**Propósito**: Sistema de etiquetado flexible para promociones, características especiales o clasificaciones transversales.

**Relaciones**:

-   Productos pueden tener múltiples etiquetas (many-to-many)

#### **variations** y **variation_items**

Sistema de variaciones de productos.

**Propósito**: Manejar productos con múltiples opciones (color, tamaño, modelo, etc.) con precios y SKUs independientes.

**variations**:

-   `name` - Nombre de la variación (ej: "Color", "Tamaño")

**variation_items**:

-   `name` - Valor específico (ej: "Rojo", "XL")
-   `variation_id` - Variación a la que pertenece

**Tabla Pivot product_item_variation**:

-   `product_id`, `variation_item_id` - Relación many-to-many
-   `price` - Precio específico para esta variación
-   `sku` - SKU específico
-   `enabled` - Estado de la variación

### 🖼️ Gestión de Medios

#### **product_images**

Imágenes de productos con ordenamiento.

**Propósito**: Almacenar múltiples imágenes por producto con capacidad de ordenamiento personalizado.

**Campos**:

-   `product_id` - Producto asociado
-   `path` - Ruta de la imagen
-   `position` - Orden de visualización

#### **banners**

Banners promocionales del sitio.

**Propósito**: Gestionar elementos promocionales visuales en diferentes secciones del sitio.

### 📊 Gestión de Inventario

#### **product_inventories**

Inventario por producto y bodega.

**Propósito**: Controlar stock disponible por producto en diferentes ubicaciones físicas.

**Campos**:

-   `product_id` - Producto
-   `bodega_code` - Código de bodega/almacén
-   `available` - Cantidad disponible
-   `reserved` - Cantidad reservada

#### **zone_warehouses**

Configuración de bodegas por zona.

**Propósito**: Asignar bodegas específicas a zonas geográficas para optimizar la logística.

### 💰 Sistema de Descuentos y Promociones

#### **bonifications**

Bonificaciones tipo "Compra X, Lleva Y".

**Propósito**: Automatizar promociones donde al comprar cierta cantidad se obtienen productos adicionales gratuitos.

**Campos**:

-   `name` - Nombre de la bonificación
-   `buy` - Cantidad a comprar
-   `get` - Cantidad gratuita a recibir
-   `max` - Límite máximo de aplicación
-   `product_id` - Producto base (opcional)

**Relaciones**:

-   Puede aplicar a un producto específico
-   Puede aplicar a múltiples productos (many-to-many)

#### **coupons**

Sistema avanzado de cupones promocionales.

**Propósito**: Gestionar descuentos flexibles con múltiples criterios de aplicación y restricciones.

**Campos Principales**:

-   `code` - Código del cupón
-   `name`, `description` - Información descriptiva
-   `type` - Tipo: monto fijo o porcentaje
-   `value` - Valor del descuento
-   `valid_from`, `valid_to` - Período de validez
-   `usage_limit_per_customer` - Límite por cliente
-   `usage_limit_per_vendor` - Límite por proveedor
-   `total_usage_limit` - Límite total
-   `current_usage` - Uso actual
-   `applies_to` - A qué aplica (carrito, producto, categoría, marca, etc.)
-   `applies_to_ids` - IDs específicos de aplicación
-   `except_*_ids` - Arrays de exclusiones
-   `minimum_amount` - Monto mínimo de compra

#### **coupon_usages**

Registro de uso de cupones.

**Propósito**: Auditoría y control de límites de uso de cupones.

### 🛒 Sistema de Órdenes

#### **orders**

Órdenes de compra principales.

**Propósito**: Registrar las compras realizadas por los usuarios con toda la información comercial y logística.

**Campos**:

-   `user_id` - Usuario comprador
-   `total` - Total de la orden
-   `discount` - Descuento aplicado
-   `status_id` - Estado de la orden
-   `zone_id` - Zona de entrega
-   `seller_id` - Vendedor asignado
-   `delivery_date` - Fecha de entrega
-   `observations` - Observaciones especiales
-   `coupon_id`, `coupon_code`, `coupon_discount` - Información de cupón aplicado
-   `request`, `response` - Datos de integración con sistemas externos

**Estados**:

-   `0` - PENDING (Pendiente)
-   `1` - PROCESSED (Procesada)
-   `2` - ERROR (Error)
-   `3` - ERROR_WEBSERVICE (Error de servicio externo)

#### **order_products**

Productos específicos dentro de cada orden.

**Propósito**: Detallar cada producto comprado con precios y descuentos aplicados al momento de la compra.

**Campos**:

-   `order_id` - Orden padre
-   `product_id` - Producto comprado
-   `variation_item_id` - Variación específica (si aplica)
-   `quantity` - Cantidad comprada
-   `price` - Precio unitario aplicado
-   `discount` - Descuento aplicado
-   `package_quantity` - Cantidad por empaque
-   `is_bonification` - Indica si es producto de bonificación

#### **order_product_bonifications**

Registro detallado de bonificaciones aplicadas.

**Propósito**: Auditoría completa de productos gratuitos otorgados por bonificaciones.

### ⚙️ Configuración del Sistema

#### **settings**

Configuraciones globales del sistema.

**Propósito**: Almacenar parámetros configurables del sistema como habilitación de inventario, cantidades mínimas, etc.

**Configuraciones Importantes**:

-   `inventory_enabled` - Habilitar gestión de inventario
-   `min_amount` - Monto mínimo de compra
-   `inventory_sync_enabled` - Sincronización automática de inventario

#### **taxes**

Tipos de impuestos aplicables.

**Propósito**: Definir diferentes tipos de impuestos para aplicar a productos según su clasificación.

#### **holidays**

Días festivos para cálculo de entregas.

**Propósito**: Excluir días no laborales del cálculo de fechas de entrega.

### 🎯 Funcionalidades Especiales

#### **featured_products**

Productos destacados en la página principal.

**Propósito**: Promocionar productos específicos en ubicaciones privilegiadas del sitio.

#### **featured_categories**

Categorías destacadas con personalización visual.

**Propósito**: Resaltar categorías importantes con imágenes y títulos personalizados.

#### **product_highlights**

Productos destacados por categoría.

**Propósito**: Permitir destacar productos específicos dentro de cada categoría con ordenamiento personalizado.

#### **contacts**

Formulario de contacto y registro de interesados.

**Propósito**: Capturar leads y solicitudes de información de potenciales clientes.

## 🔗 Relaciones Clave del Sistema

### Jerarquía de Descuentos

```
Vendor → Brand → Product
```

Los descuentos se aplican en orden de prioridad:

1. Descuento de Proveedor (mayor prioridad)
2. Descuento de Marca
3. Descuento de Producto
4. Las bonificaciones anulan todos los descuentos

### Gestión de Inventario

```
Product → ProductInventory (por bodega) ← ZoneWarehouse → Zone
```

El inventario se gestiona por producto y bodega, con asignación de bodegas específicas por zona geográfica.

### Estructura de Productos

```
Product (padre)
├── ProductImage (múltiples)
├── Category (many-to-many)
├── Variation → VariationItem (con precios específicos)
├── Product (hijos/combinaciones)
└── Bonification (promociones)
```

### Flujo de Órdenes

```
User → Order → OrderProduct → OrderProductBonification
```

Cada orden contiene productos específicos, y las bonificaciones se registran por separado para auditoría completa.

## 📈 Optimizaciones y Índices

### Índices Importantes

-   `products.sku` - Búsqueda rápida por SKU
-   `products.active` - Filtrado de productos activos
-   `products.sales_count` - Ordenamiento por más vendidos
-   `orders.user_id` - Consultas de órdenes por usuario
-   `product_inventories.bodega_code` - Consultas de inventario por bodega

### Campos Calculados

-   `sales_count` en productos se actualiza automáticamente
-   Los precios finales se calculan dinámicamente considerando descuentos jerárquicos
-   El inventario disponible considera stock de seguridad por producto/categoría

---

Esta estructura de base de datos proporciona una base sólida para un sistema de comercio B2B complejo, con flexibilidad para adaptarse a diferentes modelos de negocio manteniendo la integridad referencial y el rendimiento óptimo.
