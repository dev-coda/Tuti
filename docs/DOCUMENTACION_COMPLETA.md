# Documentación completa del sistema Tuti (visión de conjunto)

> **Índice y organización (2026):**  
> - Portal: [README.md](./README.md)  
> - **Manuales por carpeta (español):** [guias/README.md](./guias/README.md) (tienda, admin, roles)  
> - *Antigua* estructura `manuales-usuario/` [redirige a guías](./manuales-usuario/README.md)  
> - Técnica: [tecnica/README.md](./tecnica/README.md)  

## 📚 Resumen de la documentación creada o referenciada

Se ha generado una documentación completa del sistema Tuti que incluye los siguientes componentes:

### 1. README Principal (`README.md`)

**Ubicación**: Raíz del proyecto  
**Contenido**:

-   Descripción general del proyecto y su propósito
-   Tecnologías utilizadas (Laravel 10, Vue.js, Tailwind CSS, etc.)
-   Arquitectura del sistema siguiendo el patrón MVC
-   Filosofía del proyecto y principios de diseño
-   Detalles de implementación específicos
-   Configuración y despliegue
-   Funcionalidades principales del sistema B2B

### 2. Documentación de Base de Datos (`docs/DATABASE.md`)

**Ubicación**: `docs/DATABASE.md`  
**Contenido**:

-   Descripción detallada de todas las entidades de la base de datos
-   Propósito y función de cada tabla
-   Campos principales y sus significados
-   Relaciones entre entidades
-   Explicación conceptual de cada módulo:
    -   Gestión de usuarios y geografía
    -   Sistema de proveedores y marcas
    -   Catálogo de productos con variaciones
    -   Sistema de inventario por bodegas
    -   Órdenes y procesamiento
    -   Sistema de descuentos y bonificaciones
    -   Cupones promocionales
    -   Productos destacados

### 3. Diagrama de Relaciones Entre Entidades (ER)

**Visualización**: Diagrama Mermaid integrado en el chat
**Características**:

-   Muestra todas las tablas del sistema con sus campos principales
-   Relaciones claras entre entidades (1:1, 1:N, N:M)
-   Claves primarias y foráneas identificadas
-   Estructura completa de la base de datos
-   Facilita la comprensión de las dependencias entre entidades

### 4. Diagrama de Arquitectura del Sistema

**Visualización**: Diagrama Mermaid integrado en el chat
**Características**:

-   Arquitectura en capas del sistema
-   Flujo de datos desde frontend hasta base de datos
-   Servicios externos integrados
-   Sistema de colas y jobs
-   Tipos de usuarios y sus accesos
-   Tecnologías utilizadas en cada capa

## 🎯 Aspectos Destacados del Sistema

### Complejidad del Negocio

El sistema Tuti maneja un modelo de negocio B2B sofisticado que incluye:

1. **Sistema de Descuentos Jerárquico**

    - Descuentos a nivel de proveedor, marca y producto
    - Restricciones por primera compra
    - Configuración granular por entidad

2. **Gestión de Productos Complejos**

    - Productos simples y combinados
    - Variaciones con precios independientes
    - Sistema de empaques y cantidades
    - Relaciones padre-hijo entre productos

3. **Bonificaciones Automatizadas**

    - Promociones "Compra X, Lleva Y"
    - Aplicación automática en el carrito
    - Límites configurables
    - Auditoría completa de aplicación

4. **Inventario Multi-Bodega**

    - Gestión de stock por ubicación física
    - Asignación de bodegas por zona geográfica
    - Stock de seguridad configurable
    - Sincronización automática con sistemas externos

5. **Sistema de Cupones Avanzado**
    - Múltiples tipos de descuento
    - Criterios flexibles de aplicación
    - Exclusiones granulares
    - Control de límites de uso

### Arquitectura Técnica

1. **Frontend Híbrido**

    - Combinación de Blade templates, Vue.js y Livewire
    - Experiencias diferenciadas por tipo de usuario
    - Componentes reactivos para funcionalidades complejas

2. **Backend Escalable**

    - Patrón MVC extendido con servicios y repositorios
    - Sistema de colas para operaciones pesadas
    - Middleware para autenticación y autorización
    - API REST para integraciones

3. **Gestión de Datos**
    - Base de datos relacional optimizada
    - Cacheo estratégico con Redis
    - Almacenamiento de archivos local y en la nube
    - Índices optimizados para consultas frecuentes

## 📋 Entidades Principales por Módulo

### Módulo de Usuarios

-   `users` - Información completa de usuarios
-   `states`, `cities` - Estructura geográfica
-   `zones` - Zonas de entrega y rutas

### Módulo de Catálogo

-   `vendors` - Proveedores principales
-   `brands` - Marcas por proveedor
-   `products` - Catálogo principal
-   `categories` - Organización jerárquica
-   `variations`, `variation_items` - Sistema de variaciones

### Módulo de Inventario

-   `product_inventories` - Stock por producto y bodega
-   `zone_warehouses` - Asignación de bodegas

### Módulo de Ventas

-   `orders` - Órdenes principales
-   `order_products` - Detalle de productos
-   `order_product_bonifications` - Bonificaciones aplicadas

### Módulo de Promociones

-   `bonifications` - Promociones automáticas
-   `coupons` - Sistema de cupones
-   `coupon_usages` - Auditoría de uso

### Módulo de Destacados

-   `featured_products` - Productos destacados globales
-   `featured_categories` - Categorías destacadas
-   `product_highlights` - Productos destacados por categoría

## 🔧 Características Técnicas Avanzadas

### Jobs y Procesamiento Asíncrono

-   `ProcessImage` - Optimización de imágenes
-   `ProcessOrder` - Integración con ERP
-   `SyncProductInventory` - Sincronización de inventario
-   `UpdateProductPrices` - Actualización masiva de precios

### Integración con Servicios Externos

-   Sistema ERP para procesamiento de órdenes
-   Servicios de almacenamiento en la nube
-   APIs de inventario para sincronización
-   Servicios de email para notificaciones

### Optimizaciones de Rendimiento

-   Cacheo de consultas frecuentes
-   Índices estratégicos en base de datos
-   Lazy loading de relaciones
-   Paginación optimizada para listados grandes

---

Esta visión *macro* se complementa con el **índice** en [./README.md](./README.md) y con los manuales y guías técnicas enlazados allí. Mantiene el enfoque en *entidades* y módulo de negocio; el detalle operativo vive en `manuales-usuario/`, y el *runbook* de despliegue/colas, en `tecnica/`.

*Última alineación de índice: abril 2026.*
