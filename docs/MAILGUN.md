# Mailgun - Ficha Técnica Detallada

## 📋 Índice

1. [¿Qué es Mailgun?](#qué-es-mailgun)
2. [¿Por qué es necesario Mailgun?](#por-qué-es-necesario-mailgun)
3. [¿Cómo lo estamos usando en Tuti?](#cómo-lo-estamos-usando-en-tuti)
4. [Configuración Técnica](#configuración-técnica)
5. [Casos de Uso Implementados](#casos-de-uso-implementados)
6. [Ventajas y Consideraciones](#ventajas-y-consideraciones)

---

## ¿Qué es Mailgun?

**Mailgun** es un servicio de API de correo electrónico transaccional desarrollado por Sinch (anteriormente Rackspace). Es una plataforma en la nube que permite a las aplicaciones enviar, recibir y rastrear correos electrónicos de manera programática y confiable.

### Características Principales:

-   **API RESTful**: Permite enviar correos electrónicos a través de llamadas HTTP simples
-   **SMTP Relay**: Soporte para protocolos SMTP tradicionales
-   **Alta Entregabilidad**: Infraestructura optimizada para maximizar la tasa de entrega
-   **Escalabilidad**: Maneja desde cientos hasta millones de correos electrónicos
-   **Seguimiento y Análisis**: Proporciona estadísticas detalladas sobre entregas, aperturas, clics y rebotes
-   **Validación de Correos**: API para validar direcciones de correo electrónico
-   **Gestión de Listas**: Manejo de listas de correo y supresión automática
-   **Webhooks**: Notificaciones en tiempo real sobre eventos de correo electrónico

### Tipos de Transporte Disponibles:

1. **API HTTP**: Envío directo a través de la API REST de Mailgun
2. **SMTP**: Protocolo tradicional compatible con cualquier cliente de correo

---

## ¿Por qué es necesario Mailgun?

### 1. **Problemas de Correo Electrónico Directo**

Enviar correos electrónicos directamente desde un servidor de aplicación presenta múltiples desafíos:

#### Entregabilidad

-   **Reputación del IP**: Los servidores nuevos o sin reputación tienen altas tasas de rechazo
-   **Filtros de Spam**: Los correos pueden ser marcados como spam sin una infraestructura adecuada
-   **DNS y SPF**: Requiere configuración compleja de registros DNS (SPF, DKIM, DMARC)

#### Infraestructura

-   **Escalabilidad**: Enviar miles de correos simultáneamente puede sobrecargar el servidor
-   **Mantenimiento**: Gestionar colas de correo y reintentos es complejo
-   **Monitoreo**: Difícil rastrear entregas, rebotes y problemas de entrega

#### Cumplimiento

-   **Listas de Supresión**: Gestión manual de usuarios que no desean correos
-   **Regulaciones**: Cumplimiento con CAN-SPAM, GDPR y otras regulaciones
-   **Rebotes y Quejas**: Manejo automático de direcciones inválidas

### 2. **Ventajas Específicas de Mailgun**

#### Confiabilidad

-   **99.99% de Uptime**: Garantía de disponibilidad del servicio
-   **Redundancia**: Múltiples servidores y centros de datos
-   **Procesamiento Asíncrono**: No bloquea la aplicación durante el envío

#### Análisis y Monitoreo

-   **Dashboard Completo**: Visualización de todas las métricas de correo
-   **Logs Detallados**: Registro de cada correo enviado con su estado
-   **Alertas**: Notificaciones sobre problemas de entrega

#### Optimización de Costos

-   **Pago por Uso**: Solo pagas por los correos enviados
-   **Plan Gratuito**: 5,000 correos/mes gratis para empezar
-   **Sin Infraestructura**: No necesitas mantener servidores de correo

### 3. **Necesidades del Proyecto Tuti**

En una plataforma de e-commerce como Tuti, el correo electrónico es **crítico** para:

-   **Confirmaciones de Pedidos**: Los clientes esperan recibir confirmación inmediata
-   **Actualizaciones de Estado**: Notificar sobre cambios en el estado del pedido
-   **Registro de Usuarios**: Emails de bienvenida y verificación
-   **Recuperación de Contraseña**: Funcionalidad esencial de seguridad
-   **Comunicaciones Comerciales**: Notificaciones de contacto y consultas
-   **Experiencia del Cliente**: La entrega confiable de correos mejora la confianza

**Sin un servicio como Mailgun**, estos correos críticos podrían:

-   No llegar a los clientes
-   Terminar en spam
-   Fallar sin notificación
-   Sobrecargar el servidor en momentos de alto tráfico

---

## ¿Cómo lo estamos usando en Tuti?

### Arquitectura de Implementación

La implementación de Mailgun en Tuti utiliza una arquitectura flexible y robusta basada en el servicio `MailingService`.

#### Diagrama de Flujo

```
[Aplicación Laravel]
        ↓
[MailingService] ← Configuración desde Base de Datos (Settings)
        ↓
    [Laravel Mail API]
        ↓
    ┌────────┴────────┐
    ↓                 ↓
[Mailgun API]    [SMTP Mailgun]
    ↓                 ↓
[Mailgun Cloud Service]
    ↓
[Cliente Final]
```

### Componentes Principales

#### 1. **MailingService** (`app/Services/MailingService.php`)

Servicio centralizado que gestiona toda la lógica de envío de correos:

```php
class MailingService
{
    - updateMailConfiguration()      // Actualiza configuración desde BD
    - sendTemplateEmail()             // Envía correos con plantillas
    - sendOrderConfirmationEmail()    // Confirmación de pedidos
    - sendOrderStatusEmail()          // Cambios de estado de pedidos
    - sendUserRegistrationEmail()     // Registro de usuarios
    - sendContactFormNotification()   // Notificaciones de contacto
}
```

**Características:**

-   Configuración dinámica desde base de datos
-   Soporte para múltiples métodos de transporte (API y SMTP)
-   Fallback automático a SMTP si Mailgun API no está disponible
-   Sistema de plantillas con variables dinámicas
-   Manejo robusto de errores con logging

#### 2. **Configuración Dinámica**

La configuración de Mailgun se gestiona de dos formas:

**A. Variables de Entorno (`.env`)**

```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.tuti.com
MAILGUN_SECRET=key-xxxxxxxxxxxxx
MAILGUN_ENDPOINT=api.mailgun.net
```

**B. Base de Datos (Tabla `settings`)**

```php
- mail_mailer: "mailgun" o "smtp"
- mail_from_address: "noreply@tuti.com"
- mail_from_name: "Tuti"
- mailgun_domain: Dominio verificado en Mailgun
- mailgun_secret: API Key de Mailgun
- mailgun_endpoint: Endpoint regional
- smtp_host, smtp_port, smtp_username, etc.
```

**Ventaja**: Permite cambiar la configuración sin reiniciar la aplicación.

#### 3. **Sistema de Plantillas de Correo**

Se utiliza el modelo `EmailTemplate` para gestionar plantillas personalizables:

**Plantillas Implementadas:**

-   `order_confirmation`: Confirmación de pedido realizado
-   `order_status_pending`: Pedido pendiente
-   `order_status_processed`: Pedido procesado
-   `order_status_shipped`: Pedido enviado
-   `order_status_delivered`: Pedido entregado
-   `order_status_cancelled`: Pedido cancelado
-   `user_registration`: Bienvenida y activación de cuenta
-   `contact_form`: Notificación de formulario de contacto

**Variables Dinámicas Disponibles:**

```php
{order_id}          // ID del pedido
{order_status}      // Estado del pedido
{customer_name}     // Nombre del cliente
{customer_email}    // Email del cliente
{order_total}       // Total del pedido
{order_date}        // Fecha del pedido
{delivery_date}     // Fecha de entrega
{tracking_url}      // URL de seguimiento
{order_products}    // Lista de productos
{activation_link}   // Link de activación
{login_url}         // URL de login
```

### Flujo de Envío de Correos

#### Ejemplo: Confirmación de Pedido

```
1. Usuario completa una compra
   ↓
2. Se crea un Order en la base de datos
   ↓
3. Se dispara ProcessOrderAsync Job
   ↓
4. Job llama a MailingService->sendOrderConfirmationEmail()
   ↓
5. MailingService actualiza configuración desde BD
   ↓
6. Se obtiene la plantilla "order_confirmation"
   ↓
7. Se reemplazan las variables con datos del pedido
   ↓
8. Laravel Mail envía el correo a través de Mailgun
   ↓
9. Mailgun procesa y entrega el correo
   ↓
10. Se registra el resultado en logs
```

### Métodos de Transporte

#### 1. **API de Mailgun** (Recomendado)

-   Envío directo a través de HTTP/REST
-   Más rápido y eficiente
-   Mejor integración con características de Mailgun
-   Requiere paquete Symfony Mailgun Bridge

```php
'mailgun' => [
    'transport' => 'mailgun',
    'domain' => 'mg.tuti.com',
    'secret' => 'key-xxxxx',
    'endpoint' => 'api.mailgun.net',
]
```

#### 2. **SMTP de Mailgun** (Fallback)

-   Protocolo tradicional SMTP
-   Compatible con cualquier aplicación
-   Usado como respaldo si la API no está disponible
-   No requiere paquetes adicionales

```php
'smtp' => [
    'transport' => 'smtp',
    'host' => 'smtp.mailgun.org',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'postmaster@mg.tuti.com',
    'password' => 'xxxxx',
]
```

### Sistema de Fallback

La implementación incluye un mecanismo inteligente de fallback:

```php
if ($mailDriver === 'mailgun' && !$mailgunAvailable) {
    Log::warning("Mailgun package not available, falling back to SMTP");
    Config::set('mail.default', 'smtp');
}
```

**Escenarios cubiertos:**

-   Si el paquete Mailgun no está instalado → usa SMTP
-   Si la configuración de Mailgun es inválida → usa SMTP
-   Si hay error en la API → intenta con SMTP (configurado en failover)

### Integración con Jobs y Colas

Para no bloquear la aplicación, los correos se envían de forma asíncrona:

```php
// app/Jobs/SendOrderEmail.php
dispatch(new SendOrderEmail($order));
```

**Beneficios:**

-   La respuesta al usuario es inmediata
-   Los correos se procesan en segundo plano
-   Si falla, se reintenta automáticamente
-   Manejo de picos de carga

---

## Configuración Técnica

### Requisitos del Sistema

#### Composer Packages

```json
{
    "symfony/mailgun-mailer": "^6.0",
    "symfony/http-client": "^6.0",
    "guzzlehttp/guzzle": "^7.2"
}
```

#### Variables de Entorno Obligatorias

```env
# Configuración General
MAIL_MAILER=mailgun
MAIL_FROM_ADDRESS=noreply@tuti.com
MAIL_FROM_NAME="Tuti"

# Configuración Mailgun API
MAILGUN_DOMAIN=mg.tuti.com
MAILGUN_SECRET=key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
MAILGUN_ENDPOINT=api.mailgun.net

# Configuración SMTP (Fallback)
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@mg.tuti.com
MAIL_PASSWORD=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
MAIL_ENCRYPTION=tls
```

### Configuración en Mailgun Dashboard

#### 1. **Verificación de Dominio**

Para enviar desde `@tuti.com`, se deben configurar estos registros DNS:

```dns
Tipo    Host                            Valor
TXT     mg.tuti.com                     v=spf1 include:mailgun.org ~all
TXT     k1._domainkey.mg.tuti.com      [DKIM Key proporcionada por Mailgun]
CNAME   email.mg.tuti.com              mailgun.org
MX      mg.tuti.com                     mxa.mailgun.org (Priority: 10)
MX      mg.tuti.com                     mxb.mailgun.org (Priority: 10)
```

**Tiempos de Propagación**: 24-48 horas

#### 2. **Obtener Credenciales**

En el dashboard de Mailgun:

1. Ir a **Settings** → **API Keys**
2. Copiar el **Private API key** (comienza con `key-`)
3. Copiar el **SMTP Username** (ej: `postmaster@mg.tuti.com`)
4. Copiar el **SMTP Password**

#### 3. **Configurar Webhooks** (Opcional pero Recomendado)

Para recibir notificaciones de eventos:

```
URL: https://tuti.com/webhooks/mailgun
Eventos: delivered, failed, complained, unsubscribed, opened, clicked
```

### Configuración en Laravel

#### Archivos de Configuración

**config/mail.php**

```php
'mailers' => [
    'mailgun' => [
        'transport' => 'mailgun',
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],
    'smtp' => [
        'transport' => 'smtp',
        'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
        'port' => env('MAIL_PORT', 587),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
    ],
],
```

**config/services.php**

```php
'mailgun' => [
    'domain' => env('MAILGUN_DOMAIN'),
    'secret' => env('MAILGUN_SECRET'),
    'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    'scheme' => 'https',
],
```

#### Base de Datos - Tabla Settings

La configuración también se puede gestionar desde la interfaz admin:

```sql
INSERT INTO settings (key, value) VALUES
('mail_mailer', 'mailgun'),
('mail_from_address', 'noreply@tuti.com'),
('mail_from_name', 'Tuti'),
('mailgun_domain', 'mg.tuti.com'),
('mailgun_secret', 'key-xxxxx'),
('mailgun_endpoint', 'api.mailgun.net');
```

### Pruebas y Debugging

#### Comando Artisan para Probar

```bash
# Enviar un correo de prueba
php artisan tinker
>>> app(\App\Services\MailingService::class)->sendTemplateEmail('test', ['customer_email' => 'test@example.com']);
```

#### Verificar Logs

```bash
# Ver logs de Laravel
tail -f storage/logs/laravel.log | grep -i mail

# Buscar errores de Mailgun
tail -f storage/logs/laravel.log | grep -i mailgun
```

#### Dashboard de Mailgun

1. Ir a **Sending** → **Logs**
2. Filtrar por dominio y fecha
3. Ver detalles de cada correo enviado

---

## Casos de Uso Implementados

### 1. **Confirmación de Pedido**

**Trigger**: Cuando un usuario completa una compra

**Método**: `sendOrderConfirmationEmail(Order $order)`

**Datos Incluidos:**

-   Número de pedido
-   Nombre del cliente
-   Lista de productos comprados con cantidades y precios
-   Total del pedido
-   Fecha de pedido
-   Fecha estimada de entrega
-   Link de seguimiento

**Plantilla**: `order_confirmation`

**Código de Ejemplo:**

```php
$mailingService = new MailingService();
$mailingService->sendOrderConfirmationEmail($order);
```

---

### 2. **Actualizaciones de Estado del Pedido**

**Trigger**: Cuando el estado de un pedido cambia

**Método**: `sendOrderStatusEmail(Order $order, string $newStatus)`

**Estados Soportados:**

-   `pending`: Pedido pendiente de procesamiento
-   `processed`: Pedido procesado y preparado
-   `shipped`: Pedido enviado al cliente
-   `delivered`: Pedido entregado exitosamente
-   `cancelled`: Pedido cancelado

**Datos Incluidos:**

-   Número de pedido
-   Estado actual (en español)
-   Nombre del cliente
-   Total del pedido
-   Fecha de cambio de estado
-   Link de seguimiento

**Plantillas**:

-   `order_status_pending`
-   `order_status_processed`
-   `order_status_shipped`
-   `order_status_delivered`
-   `order_status_cancelled`

**Código de Ejemplo:**

```php
$mailingService = new MailingService();
$mailingService->sendOrderStatusEmail($order, 'shipped');
```

---

### 3. **Registro de Usuario**

**Trigger**: Cuando un nuevo usuario se registra

**Método**: `sendUserRegistrationEmail(User $user)`

**Datos Incluidos:**

-   Nombre del usuario
-   Email del usuario
-   Link de activación/verificación de cuenta
-   Link de inicio de sesión
-   Mensaje de bienvenida

**Plantilla**: `user_registration`

**Características:**

-   Token de activación seguro generado con HMAC
-   Link de verificación con hash de email
-   Instrucciones para el primer inicio de sesión

**Código de Ejemplo:**

```php
$mailingService = new MailingService();
$mailingService->sendUserRegistrationEmail($newUser);
```

---

### 4. **Notificación de Formulario de Contacto**

**Trigger**: Cuando alguien completa el formulario de contacto

**Método**: `sendContactFormNotification(Contact $contact)`

**Datos Incluidos:**

-   Nombre del contacto
-   Email del contacto
-   Teléfono
-   Nombre de la empresa
-   Ciudad
-   NIT
-   Mensaje/consulta
-   Fecha y hora del contacto

**Plantilla**: `contact_form`

**Características:**

-   Se envía a múltiples administradores
-   Incluye todos los datos del formulario
-   Permite respuesta directa al contacto

**Destinatarios**: Array de emails de administradores (configurable)

**Código de Ejemplo:**

```php
$mailingService = new MailingService();
$mailingService->sendContactFormNotification($contact);
```

---

### 5. **Recuperación de Contraseña** (Nativo de Laravel)

**Trigger**: Cuando un usuario solicita restablecer su contraseña

**Implementación**: Usa el sistema nativo de Laravel con Mailgun como transporte

**Características:**

-   Token de recuperación con expiración (60 minutos)
-   Link seguro para restablecer contraseña
-   Automático a través de `Auth::routes()`

---

## Ventajas y Consideraciones

### ✅ Ventajas de Nuestra Implementación

#### 1. **Flexibilidad**

-   Configuración dinámica desde base de datos
-   No requiere reiniciar la aplicación para cambios
-   Soporte para múltiples métodos de transporte

#### 2. **Robustez**

-   Sistema de fallback automático a SMTP
-   Manejo completo de errores con logging
-   Validación de configuración antes de enviar

#### 3. **Mantenibilidad**

-   Código centralizado en MailingService
-   Plantillas editables desde el admin panel
-   Separación clara de responsabilidades

#### 4. **Escalabilidad**

-   Procesamiento asíncrono con Jobs
-   Cola de correos para alto volumen
-   No bloquea las respuestas de la aplicación

#### 5. **Trazabilidad**

-   Logs detallados de cada envío
-   Integración con dashboard de Mailgun
-   Métricas en tiempo real

### ⚠️ Consideraciones Importantes

#### 1. **Limitaciones del Plan**

-   **Plan Gratuito**: 5,000 correos/mes (primeros 3 meses), luego 1,000/mes
-   **Plan Flex**: $0.80 por 1,000 correos
-   **Monitorear Uso**: Dashboard de Mailgun muestra consumo

#### 2. **Verificación de Dominio**

-   Dominio debe estar verificado antes de enviar
-   Requiere acceso a configuración DNS
-   Propagación puede tomar 24-48 horas

#### 3. **Dependencias**

-   Requiere paquetes Composer específicos
-   Verificar compatibilidad con versión de Laravel
-   Mantener paquetes actualizados

#### 4. **Seguridad**

-   **API Keys**: Nunca commitear en repositorio
-   Usar variables de entorno
-   Rotar keys periódicamente
-   Limitar permisos de API keys

#### 5. **Gestión de Rebotes**

-   Configurar webhooks para manejar rebotes
-   Limpiar listas de correo periódicamente
-   Respetar supresiones automáticas de Mailgun

#### 6. **Cumplimiento Legal**

-   **CAN-SPAM**: Incluir link de desuscripción
-   **GDPR**: Obtener consentimiento para marketing
-   **Registro**: Mantener logs de envíos

#### 7. **Rendimiento**

-   Usar Jobs para correos no críticos
-   Evitar bucles que envíen múltiples correos
-   Implementar throttling si es necesario

### 📊 Métricas a Monitorear

#### En Mailgun Dashboard:

-   **Delivery Rate**: Debe ser > 95%
-   **Bounce Rate**: Debe ser < 5%
-   **Complaint Rate**: Debe ser < 0.1%
-   **Open Rate**: Varía según tipo de correo
-   **Click Rate**: Varía según contenido

#### En Laravel Logs:

-   Errores de conexión
-   Timeouts
-   Correos no enviados
-   Excepciones del MailingService

### 🔄 Proceso de Mantenimiento

#### Mensual:

-   Revisar métricas de entregabilidad
-   Verificar consumo del plan
-   Analizar correos rebotados
-   Limpiar listas de supresión

#### Trimestral:

-   Auditar plantillas de correo
-   Revisar configuración de DNS
-   Actualizar paquetes de Mailgun
-   Revisar logs de errores

#### Anual:

-   Evaluar plan de Mailgun vs. necesidades
-   Revisar políticas de privacidad
-   Auditoría de seguridad de API keys
-   Optimización de plantillas

---

## 📚 Recursos Adicionales

### Documentación Oficial

-   [Mailgun Documentation](https://documentation.mailgun.com/)
-   [Laravel Mail Documentation](https://laravel.com/docs/10.x/mail)
-   [Mailgun API Reference](https://documentation.mailgun.com/en/latest/api_reference.html)

### Dashboard Mailgun

-   URL: [https://app.mailgun.com](https://app.mailgun.com)
-   Sección de Logs: Para ver correos enviados
-   Sección de Analytics: Para métricas detalladas

### Soporte

-   **Mailgun Support**: support@mailgun.com
-   **Documentación**: Extensa base de conocimiento online
-   **Status Page**: [https://status.mailgun.com](https://status.mailgun.com)

---

## 🎯 Conclusión

Mailgun es una pieza fundamental de la infraestructura de Tuti, garantizando que las comunicaciones críticas con los clientes sean:

-   ✉️ **Entregadas**: Alta tasa de entregabilidad
-   ⚡ **Rápidas**: Procesamiento inmediato
-   📊 **Rastreables**: Métricas completas
-   💰 **Económicas**: Pago por uso
-   🔒 **Seguras**: Encriptación y autenticación

La implementación actual proporciona una base sólida y escalable para las necesidades de correo electrónico de la plataforma, con flexibilidad para adaptarse a futuros requerimientos.

---

**Documento creado**: Octubre 2025  
**Última actualización**: Octubre 2025  
**Mantenido por**: Equipo de Desarrollo Tuti
