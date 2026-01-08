# Módulo de Usuarios y Autenticación

## 📋 Descripción General

El módulo de Usuarios y Autenticación gestiona todos los aspectos relacionados con los usuarios del sistema, incluyendo autenticación, roles, permisos y perfiles.

## 👥 Tipos de Usuarios

La plataforma Tuti maneja tres tipos principales de usuarios:

### 1. Administrador (`admin`)
- Acceso completo al sistema
- Gestión de todos los módulos
- Configuración del sistema
- Reportes y análisis

### 2. Vendedor (`seller`)
- Acceso a panel de vendedor
- Gestión de clientes asignados
- Visualización de órdenes de sus clientes
- Reportes de ventas

### 3. Cliente (`shopper`)
- Acceso al catálogo de productos
- Realización de compras
- Gestión de perfil
- Historial de órdenes

## 🔐 Autenticación

### Registro de Usuarios

#### Para Clientes (Shoppers)

1. **Acceso al Registro**
   - Navegar a la página de registro desde el menú principal
   - URL: `/register`

2. **Datos Requeridos**
   - Nombre completo
   - Email (debe ser único)
   - Contraseña (mínimo 8 caracteres)
   - Confirmación de contraseña
   - Documento de identidad
   - Teléfono (opcional)

3. **Proceso de Registro**
   - Completar el formulario
   - Verificar email (si está habilitado)
   - Iniciar sesión automáticamente después del registro

#### Para Vendedores y Administradores

Los vendedores y administradores son creados exclusivamente por otros administradores desde el panel de administración.

### Inicio de Sesión

1. **Acceso**
   - URL: `/login`
   - Botón "Iniciar Sesión" en el menú principal

2. **Credenciales**
   - Email registrado
   - Contraseña

3. **Recordar Sesión**
   - Opción "Recordarme" para mantener la sesión activa

4. **Recuperación de Contraseña**
   - Enlace "¿Olvidaste tu contraseña?"
   - Ingresar email para recibir enlace de recuperación
   - El enlace expira después de 60 minutos

### Cierre de Sesión

- Botón "Cerrar Sesión" en el menú de usuario
- Cierra la sesión inmediatamente
- Redirige a la página de inicio

## 👤 Gestión de Perfil

### Edición de Perfil

1. **Acceso**
   - Menú de usuario → "Mi Perfil"
   - URL: `/profile`

2. **Datos Editables**
   - Nombre completo
   - Email (requiere verificación si cambia)
   - Teléfono
   - Dirección
   - Ciudad y Estado

3. **Cambio de Contraseña**
   - Sección "Cambiar Contraseña"
   - Requiere contraseña actual
   - Nueva contraseña debe tener mínimo 8 caracteres

### Verificación de Email

- Se envía un email de verificación al registrarse
- Si no se verifica, se puede reenviar desde el perfil
- Algunas funcionalidades requieren email verificado

## 🎭 Roles y Permisos

### Sistema de Roles

El sistema utiliza **Spatie Laravel Permission** para gestionar roles y permisos.

#### Roles Disponibles

1. **admin**
   - Acceso completo
   - Puede crear/editar/eliminar cualquier recurso
   - Acceso a configuración del sistema

2. **seller**
   - Acceso limitado a panel de vendedor
   - Solo ve clientes asignados
   - Puede ver órdenes de sus clientes

3. **shopper**
   - Acceso público al catálogo
   - Puede realizar compras
   - Gestión de su propio perfil

### Permisos

Los permisos se asignan automáticamente según el rol. Los administradores pueden gestionar permisos desde el panel de administración.

## 🔑 Gestión de Usuarios (Administradores)

### Crear Usuario

1. **Navegación**
   - Panel Admin → Usuarios → Crear Usuario

2. **Datos Requeridos**
   - Nombre completo
   - Email (único)
   - Contraseña
   - Rol (admin, seller, shopper)
   - Documento de identidad
   - Teléfono (opcional)

3. **Asignación de Vendedor**
   - Si el rol es "seller", se puede asignar a un vendedor específico
   - Los clientes asignados solo serán visibles para ese vendedor

### Editar Usuario

1. **Acceso**
   - Panel Admin → Usuarios → Lista → Editar

2. **Campos Editables**
   - Todos los campos del perfil
   - Rol (con precaución)
   - Estado activo/inactivo
   - Asignación de vendedor

3. **Restricciones**
   - No se puede cambiar el email si el usuario tiene órdenes
   - Cambiar el rol puede afectar el acceso

### Eliminar Usuario

⚠️ **Advertencia**: Eliminar un usuario es una acción irreversible.

1. **Consideraciones**
   - Si el usuario tiene órdenes, no se puede eliminar completamente
   - Se marca como inactivo en lugar de eliminar
   - Los datos históricos se mantienen

2. **Proceso**
   - Panel Admin → Usuarios → Lista → Eliminar
   - Confirmar la acción

### Listar Usuarios

- Panel Admin → Usuarios
- Filtros disponibles:
  - Por rol
  - Por estado (activo/inactivo)
  - Por vendedor asignado
  - Búsqueda por nombre o email

## 📍 Zonas y Rutas de Usuario

### Sincronización de Rutas

Los usuarios tienen zonas asignadas que determinan:
- Bodega de inventario
- Fechas de entrega disponibles
- Rutas de entrega

#### Sincronización Automática

- Se sincroniza automáticamente al procesar una orden
- Obtiene datos del sistema externo (Ruteros)
- Actualiza zonas del usuario

#### Gestión Manual

Los administradores pueden:
- Ver zonas asignadas a un usuario
- Sincronizar manualmente desde el perfil del usuario
- Asignar zonas manualmente si es necesario

### Zonas del Usuario

Cada usuario puede tener múltiples zonas asociadas con:
- **Código de zona**: Identificador único
- **Ruta**: Ruta de entrega
- **Día**: Día de la semana de entrega
- **Dirección**: Dirección asociada

## 🔒 Seguridad

### Contraseñas

- Mínimo 8 caracteres
- Se almacenan con hash bcrypt
- No se pueden recuperar, solo resetear

### Sesiones

- Las sesiones expiran después de inactividad
- "Recordarme" extiende la duración
- Se puede cerrar sesión desde cualquier dispositivo

### Protección CSRF

- Todos los formularios incluyen tokens CSRF
- Protección automática contra ataques CSRF

## 📊 Reglas de Negocio

### Primera Compra

- Los usuarios sin órdenes previas se consideran "primera compra"
- Pueden acceder a descuentos especiales de primera compra
- Después de la primera orden, pierden este beneficio

### Validación de Documento

- El documento debe ser único en el sistema
- Se usa para sincronización con sistemas externos
- No se puede cambiar después de crear órdenes

### Estado de Usuario

- **Activo**: Puede iniciar sesión y realizar acciones
- **Inactivo**: No puede iniciar sesión, pero los datos se mantienen

## 🚀 Funcionalidades Avanzadas

### Asignación de Vendedores

- Los vendedores solo ven clientes asignados
- Un cliente puede tener un vendedor asignado
- Los administradores pueden cambiar la asignación

### Historial de Actividad

- Se registran acciones importantes del usuario
- Disponible en el perfil del usuario (para administradores)
- Incluye fechas de registro, última sesión, etc.

## ❓ Preguntas Frecuentes

### ¿Puedo cambiar mi email después de registrarme?

Sí, pero requiere verificación del nuevo email. Si tienes órdenes, el cambio puede requerir aprobación del administrador.

### ¿Qué pasa si olvido mi contraseña?

Usa la opción "¿Olvidaste tu contraseña?" en la página de login. Recibirás un email con un enlace para crear una nueva contraseña.

### ¿Puedo tener múltiples cuentas con el mismo email?

No, cada email solo puede estar asociado a una cuenta.

### ¿Cómo cambio mi rol?

Solo los administradores pueden cambiar roles. Contacta a un administrador si necesitas cambiar tu rol.

### ¿Qué son las zonas y por qué las necesito?

Las zonas determinan tu bodega de inventario y las fechas de entrega disponibles. Se sincronizan automáticamente desde el sistema externo basado en tu documento de identidad.

## 📝 Notas Técnicas

- Los usuarios se almacenan en la tabla `users`
- Las sesiones se gestionan con Laravel Breeze
- Los roles y permisos usan Spatie Laravel Permission
- Las zonas se sincronizan desde sistema externo vía SOAP

