# Tuti - Sistema de Comercio Electrónico B2B

## 📋 Descripción del Proyecto

Tuti es una plataforma de comercio electrónico B2B especializada en la venta de productos a tenderos y vendedores. El sistema está diseñado para gestionar un catálogo complejo de productos con múltiples variaciones, bonificaciones, descuentos por volumen, y un sistema robusto de gestión de inventario.

## 🛠️ Tecnologías Utilizadas

### Backend

-   **Laravel 10.x** - Framework principal de PHP
-   **PHP 8.1+** - Lenguaje de programación
-   **MySQL/MariaDB** - Base de datos relacional
-   **Laravel Sanctum** - Autenticación API
-   **Laravel Horizon** - Gestión de colas de trabajo
-   **Laravel Breeze** - Autenticación de usuarios
-   **Spatie Laravel Permission** - Sistema de roles y permisos

### Frontend

-   **Blade Templates** - Motor de plantillas de Laravel
-   **Vue.js 3** - Framework de JavaScript para componentes reactivos
-   **Alpine.js** - Framework ligero de JavaScript
-   **Tailwind CSS** - Framework de CSS utilitario
-   **Vite** - Bundler de assets
-   **Livewire 2** - Componentes dinámicos full-stack

### Librerías y Paquetes Especializados

-   **Intervention Image** - Procesamiento de imágenes
-   **Maatwebsite Excel** - Importación/exportación de Excel
-   **Blade Heroicons** - Iconografía
-   **GLHD Aire** - Formularios mejorados
-   **Spatie Array to XML** - Conversión de datos

### Herramientas de Desarrollo

-   **Pest PHP** - Framework de testing
-   **Laravel Pint** - Code styling
-   **Laravel Sail** - Entorno de desarrollo con Docker

## 🏗️ Arquitectura del Sistema

### Patrón Arquitectónico

El proyecto sigue el patrón **MVC (Model-View-Controller)** de Laravel con algunas extensiones:

```
app/
├── Console/           # Comandos Artisan
├── Http/
│   ├── Controllers/   # Controladores organizados por dominio
│   ├── Middleware/    # Middleware personalizado
│   ├── Requests/      # Form Requests para validación
│   └── Livewire/      # Componentes Livewire
├── Models/            # Modelos Eloquent
├── Jobs/              # Jobs para colas
├── Mail/              # Clases de correo
├── Services/          # Servicios de negocio
├── Repositories/      # Repositorios de datos
└── Providers/         # Service Providers
```

### Estructura de Controladores

Los controladores están organizados por contexto:

-   **Admin/** - Gestión administrativa del sistema
-   **Api/** - Endpoints de API
-   **Auth/** - Autenticación y autorización
-   **Seller/** - Funcionalidades específicas para vendedores
-   **Shopper/** - Funcionalidades para tenderos

### Sistema de Rutas

-   **web.php** - Rutas públicas y de usuarios autenticados
-   **admin.php** - Rutas administrativas protegidas
-   **api.php** - Endpoints de API
-   **auth.php** - Rutas de autenticación

## 🎯 Filosofía del Proyecto

### Principios de Diseño

1. **Separación de Responsabilidades**

    - Cada modelo tiene responsabilidades específicas y bien definidas
    - Los controladores se mantienen ligeros delegando lógica a servicios
    - Las validaciones se centralizan en Form Requests

2. **Escalabilidad**

    - Sistema de colas para operaciones pesadas (procesamiento de imágenes, sincronización de inventario)
    - Uso de Jobs para tareas asíncronas
    - Cacheo estratégico de consultas frecuentes

3. **Flexibilidad de Negocios**

    - Sistema de descuentos multicapa (producto, marca, vendedor)
    - Bonificaciones configurables (compra X, lleva Y)
    - Gestión granular de inventario por bodega
    - Configuración flexible de productos con variaciones

4. **Experiencia de Usuario**
    - Interfaces diferenciadas para vendedores y tenderos
    - Búsqueda avanzada con múltiples filtros
    - Carrito de compras persistente
    - Sistema de cupones promocionales

### Modelos de Negocio Implementados

#### Sistema de Descuentos Jerárquico

1. **Nivel Producto** - Descuentos específicos por producto
2. **Nivel Marca** - Descuentos aplicables a toda una marca
3. **Nivel Vendedor** - Descuentos globales del proveedor
4. **Restricciones por Primera Compra** - Control de descuentos para nuevos clientes

#### Gestión de Inventario

-   **Inventario por Bodega** - Múltiples ubicaciones de stock
-   **Stock de Seguridad** - Niveles mínimos configurables
-   **Sincronización Automática** - Jobs para actualizar inventario
-   **Opt-out Selectivo** - Productos que no requieren gestión de inventario

## 🔧 Detalles de Implementación

### Sistema de Productos

#### Productos Simples vs Combinados

-   **Productos Simples**: Un solo SKU, precio fijo
-   **Productos Combinados**: Múltiples variaciones con precios independientes
-   **Productos Padre/Hijo**: Relaciones jerárquicas para empaques

#### Variaciones de Producto

```php
Product -> Variation -> VariationItems
```

-   Un producto puede tener múltiples items de variación
-   Cada item tiene precio, SKU y estado independientes
-   Soporte para productos con múltiples opciones (color, tamaño, etc.)

### Sistema de Bonificaciones

Las bonificaciones siguen el modelo "Compra X, Lleva Y":

-   Configuración flexible de cantidades
-   Límites máximos por pedido
-   Aplicación automática en el carrito
-   Registro detallado de bonificaciones aplicadas

### Gestión de Órdenes

#### Estados de Orden

-   `PENDING (0)` - Orden creada, pendiente de procesamiento
-   `PROCESSED (1)` - Orden procesada exitosamente
-   `ERROR (2)` - Error en el procesamiento
-   `ERROR_WEBSERVICE (3)` - Error en servicios externos

#### Flujo de Procesamiento

1. Creación de orden desde carrito
2. Validación de inventario disponible
3. Aplicación de descuentos y bonificaciones
4. Integración con servicios externos (ERP)
5. Confirmación y notificaciones

### Sistema de Usuarios y Roles

#### Tipos de Usuario

-   **Admin** - Acceso completo al sistema
-   **Seller** - Vendedores con acceso limitado
-   **Customer** - Tenderos compradores

#### Gestión Geográfica

-   **Estados y Ciudades** - Ubicación de usuarios
-   **Zonas de Entrega** - Rutas y días de visita
-   **Asignación de Vendedores** - Por zona geográfica

### Sistema de Cupones

Implementación avanzada de cupones promocionales:

-   **Tipos**: Monto fijo o porcentaje
-   **Aplicabilidad**: Carrito completo, productos específicos, categorías, marcas
-   **Restricciones**: Por usuario, vendor, límites totales
-   **Exclusiones**: Productos, categorías, marcas, tipos de usuario

### Procesamiento de Imágenes

-   **Subida Asíncrona**: Jobs para procesar imágenes
-   **Múltiples Formatos**: Soporte para diferentes tamaños
-   **Optimización Automática**: Compresión y redimensionado
-   **Orden Configurable**: Posicionamiento de imágenes por producto

### Sistema de Colas y Jobs

#### Jobs Implementados

-   `ProcessImage` - Procesamiento de imágenes de productos
-   `ProcessOrder` - Procesamiento asíncrono de órdenes
-   `SyncProductInventory` - Sincronización de inventario
-   `UpdateProductPrices` - Actualización masiva de precios

### Exportaciones e Importaciones

-   **Excel Integration**: Exportación de usuarios, productos, órdenes
-   **Importación de Estados**: Carga masiva de datos geográficos
-   **Sincronización de Bodegas**: Importación de datos de almacén

## 🚀 Configuración y Despliegue

### Requisitos del Sistema

-   PHP 8.1 o superior
-   Composer
-   Node.js 16+ y NPM
-   MySQL 8.0 o MariaDB 10.3+
-   Redis (para colas y cache)

### Variables de Entorno Importantes

```env
# Configuración de descuentos
ENFORCE_FIRST_PURCHASE_DISCOUNTS=true
FIRST_ORDER_DISCOUNT_ENABLED=true

# Inventario
INVENTORY_ENABLED=true

# Colas
QUEUE_CONNECTION=redis
HORIZON_ENVIRONMENT=production
```

### Comandos Artisan Personalizados

-   `getToken` - Obtención de tokens de autenticación
-   `importStates` - Importación de datos geográficos
-   `ImportZoneWarehouses` - Sincronización de bodegas

## 📊 Métricas y Análisis

### Seguimiento de Ventas

-   **sales_count** en productos para ranking de más vendidos
-   Análisis de productos destacados por categoría
-   Reportes de rendimiento por marca y vendedor

### Gestión de Destacados

-   **Productos Destacados**: Configuración manual de productos principales
-   **Categorías Destacadas**: Promoción de categorías específicas
-   **Orden Personalizable**: Posicionamiento estratégico en frontend

## 🔒 Seguridad y Permisos

### Autenticación

-   Laravel Sanctum para API tokens
-   Middleware de autenticación en rutas sensibles
-   Verificación de roles por contexto (admin, seller, customer)

### Validación de Datos

-   Form Requests para validación de entrada
-   Sanitización de datos de usuario
-   Validación de integridad en relaciones de base de datos

---

Este sistema representa una solución completa para comercio B2B con énfasis en la flexibilidad de negocio, escalabilidad técnica y experiencia de usuario optimizada para el mercado de tenderos y distribuidores.
