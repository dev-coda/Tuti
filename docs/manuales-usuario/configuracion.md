# Módulo de Configuración

## 📋 Descripción General

El módulo de Configuración permite gestionar todos los aspectos generales del sistema, incluyendo modo vacaciones, configuración de email, sincronización de inventario y tokens de integración.

## 🏖️ Modo Vacaciones

### Activar Modo Vacaciones

1. **Panel Admin → Configuración → Modo Vacaciones**

2. **Configuración**:
   - **Activar Modo Vacaciones**: Toggle para activar/desactivar
   - **Fecha Inicio**: Fecha desde la cual aplica
   - **Fecha Fin**: Fecha hasta la cual aplica

3. **Comportamiento**:
   - Cuando está activo:
     - Se muestra mensaje en página principal
     - Los usuarios no pueden agregar productos al carrito
     - Se muestra fecha de retorno
   - Cuando está inactivo:
     - Funcionamiento normal

### Mensaje de Vacaciones

- Se muestra automáticamente cuando el modo está activo
- Incluye fecha de retorno
- Se puede personalizar desde configuración

## 📧 Configuración de Email

### Configuración de Servidor

1. **Panel Admin → Configuración → Email**

2. **Configuración SMTP**:
   - **Servidor SMTP**: Dirección del servidor
   - **Puerto**: Puerto SMTP (normalmente 587 o 465)
   - **Usuario**: Usuario SMTP
   - **Contraseña**: Contraseña SMTP
   - **Encriptación**: TLS o SSL

### Configuración de Mailgun (Alternativa)

Si usas Mailgun:

1. **Mailgun Domain**: Tu dominio de Mailgun
2. **Mailgun Secret**: Tu API key de Mailgun
3. **Mailgun Endpoint**: Endpoint de Mailgun (default: api.mailgun.net)

### Configuración de Remitente

- **Email Remitente**: Email desde el cual se envían los emails
- **Nombre Remitente**: Nombre que aparece como remitente

### Plantillas de Email

- **Panel Admin → Plantillas de Email**
- Editar plantillas de:
  - Confirmación de orden
  - Estado de orden
  - Recuperación de contraseña
  - Otros emails del sistema

## 📦 Configuración de Inventario

### Gestión Global de Inventario

1. **Panel Admin → Configuración → Inventario**

2. **Opciones**:
   - **Gestión de Inventario Habilitada**: Activar/desactivar gestión global
   - **Stock de Seguridad por Defecto**: Valor por defecto para productos sin stock de seguridad específico
   - **Sincronización Automática Habilitada**: Activar sincronización nocturna

### Sincronización de Inventario

1. **Configuración**:
   - Activar/desactivar sincronización automática
   - Configurar hora de sincronización (si aplica)

2. **Sincronización Manual**:
   - Botón "Sincronizar Ahora"
   - Opción síncrona (espera) o asíncrona (cola)

3. **Logs de Sincronización**:
   - Ver últimas sincronizaciones
   - Estadísticas por bodega
   - Respuestas completas del servicio SOAP

## 🔐 Tokens y Credenciales

### Token de Microsoft (ERP)

El sistema usa un token para comunicarse con el ERP externo:

1. **Renovación Automática**:
   - Se renueva automáticamente cada 30 minutos
   - Se guarda en configuración

2. **Renovación Manual**:
   - Panel Admin → Configuración → Tokens
   - Botón "Renovar Token"
   - Útil si hay problemas de comunicación

3. **Configuración**:
   - **Client ID**: ID de cliente de Microsoft
   - **Client Secret**: Secret de cliente
   - **Resource URL**: URL del recurso
   - **Token URL**: URL para obtener token

## ⚙️ Configuración General

### Configuración de Cierre

1. **Hora de Cierre**:
   - Hora después de la cual no se procesan órdenes para el mismo día
   - Afecta cálculo de fechas de entrega

2. **Días Festivos**:
   - Panel Admin → Días Festivos
   - Agregar días no laborables
   - Afecta cálculo de fechas de entrega

### Configuración de Descuentos

1. **Descuento de Primera Compra Habilitado**:
   - Activar/desactivar sistema de descuentos de primera compra
   - Si está desactivado, no se aplican descuentos de primera compra

### Configuración de Precios

1. **Actualización Automática de Precios**:
   - Job que actualiza precios desde sistema externo
   - Se ejecuta automáticamente según configuración
   - Se puede ejecutar manualmente

## 📊 Logs y Monitoreo

### Logs de SOAP

- Panel Admin → Configuración → Logs
- Muestra últimas comunicaciones con ERP
- Incluye requests y responses XML
- Útil para debugging

### Logs de Inventario

- Panel Admin → Configuración → Logs de Inventario
- Muestra últimas sincronizaciones
- Estadísticas por bodega
- Respuestas completas del servicio

### Logs de Aplicación

- Archivos de log en `storage/logs/`
- `laravel.log`: Logs generales
- `soap.log`: Logs de comunicación SOAP
- Útiles para debugging

## 🔄 Jobs y Procesamiento Asíncrono

### Configuración de Colas

El sistema usa Laravel Horizon para gestionar colas:

1. **Colas Disponibles**:
   - `default`: Procesamiento de órdenes
   - `emails`: Envío de emails
   - `inventory`: Sincronización de inventario

2. **Configuración**:
   - `config/horizon.php`
   - Configuración de workers por cola
   - Timeouts y reintentos

### Monitoreo de Horizon

- Panel Admin → Horizon (si está habilitado)
- Ver estado de colas
- Ver jobs en proceso
- Ver jobs fallidos

## 🛠️ Mantenimiento

### Limpiar Cache

1. **Cache de Configuración**:
   ```bash
   php artisan config:cache
   ```

2. **Cache de Aplicación**:
   ```bash
   php artisan cache:clear
   ```

3. **Cache de Rutas**:
   ```bash
   php artisan route:cache
   ```

### Optimizar Base de Datos

1. **Optimizar Tablas**:
   ```bash
   php artisan db:optimize
   ```

2. **Limpiar Jobs Fallidos**:
   - Panel Admin → Jobs → Limpiar Fallidos

## ⚠️ Consideraciones Importantes

### Cambios en Configuración

- Algunos cambios requieren limpiar cache
- Algunos cambios requieren reiniciar Horizon
- Siempre verificar después de cambios importantes

### Tokens y Credenciales

- No compartir credenciales
- Renovar tokens periódicamente
- Verificar que las URLs sean correctas

### Modo Vacaciones

- Activar antes de las vacaciones
- Verificar fechas de inicio y fin
- Desactivar al regresar

## ❓ Preguntas Frecuentes

### ¿Cómo activo el modo vacaciones?

Panel Admin → Configuración → Modo Vacaciones → Activar y configurar fechas.

### ¿Dónde veo los logs de sincronización?

Panel Admin → Configuración → Logs de Inventario.

### ¿Cómo renuevo el token de Microsoft?

Panel Admin → Configuración → Tokens → Renovar Token (o esperar renovación automática).

### ¿Qué pasa si desactivo la gestión de inventario?

Ningún producto gestionará inventario, todos aparecerán disponibles.

### ¿Cómo veo si hay problemas con las colas?

Revisa Laravel Horizon o los logs en `storage/logs/laravel.log`.

