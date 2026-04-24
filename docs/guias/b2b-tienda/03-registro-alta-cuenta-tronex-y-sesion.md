# Registro, alta, Tronex y sesión (Web)

Audiencia: operaciones, soporte y compradores. Referencias: `Tuti/routes/auth.php`, `Tuti/routes/web.php`, `TronexMigrationController`, y el middleware que obliga a completar el perfil tras la migración Tronex.

> **Importante:** en este proyecto, la ruta con nombre `login` en GET **redirige** al formulario B2B (`/formulario`), no a un formulario de inicio de sesión clásico en la misma URL. La autenticación por contraseña sigue siendo el `POST` de login definido en `auth.php` (`AuthenticatedSessionController@store`).

## 1. Tres vías hacia un usuario identificado (no se deben mezclar al orientar a un tendero)

### A) Formulario B2B (`/formulario`)

- **Rutas:** `form`, `form_post`, `form.check-existing` (NIT o documento duplicado), `form.cities-by-state` (cascada departamento / ciudad).  
- **Rol de negocio:** captura de interesados o inicio de alta; puede generar tareas o leads según el flujo. **No** confundir con la creación automática de una contraseña para comprar.  
- **Quien documenta** debe alinear con producto/operación si, tras aprobar el lead, se crea usuario por otro canal.

### B) Registro con contraseña (`register` / `complete` en `auth.php`)

- Flujo estándar Breeze: email, contraseña, verificación de email (si aplica y está habilitada en el modelo de usuario).  
- Úsese cuando se dé de alta a un usuario que ya debe acceder a `/ordenes` y al carrito.

### C) Migración Tronex

- `POST /tronex/migrate`: búsqueda/validación por documento y vía de verificación (teléfono); en el buen término, puede autenticar o preparar al usuario para el siguiente paso.  
- `GET` y `POST` `/tronex/completar-perfil` (bajo `middleware` `auth`): fija **email y contraseña** definitivos y levanta el indicador de migración pendiente.

Mientras un usuario tenga el perfil de migración pendiente, el **middleware** de la aplicación lo mantiene en un circuito de rutas acotado (pantalla de completar, guardar, cerrar sesión) hasta concluir.

## 2. Cierre de sesión, recuperación de clave, magic link, verificación de email

| Flujo | Rutas (nombres habituales) | Notas operativas |
|-------|----------------------------|------------------|
| Olvidé la contraseña | `password.request` / `password.email` y `password.reset` / `password.store` | Enseñar al usuario a abrir el correo y, si no llega, revisar Mailgun, spam y dominio. |
| Magic link (sin contraseña) | `magic-link.send`, `magic-link.verify` | Límites de `throttle` (ej. 6 o 10 intentos por minuto) para abuso. |
| Verificar email | `verification.notice`, `verification.verify`, reenvío | Puede afectar si exigen email verificado antes de comprar; confirmar con la instancia. |

## 3. Procedimiento Tronex (para mesa de soporte)

1. **Migrar:** el usuario llena el paso 1/2 (documento, luego contacto) según la propia UI; el back-end habla con la fuente de datos de negocio.  
2. **Si queda autenticado y pendiente:** cualquier ruta de negocio debería redirigir a `tronex.completar-perfil` (salvo lista blanca: guardar, logout, etc. según el middleware).  
3. **Completar perfil:** email único, contraseña, confirmación. Al guardar, el flag de bloqueo desaparece.  
4. **Si se queda atascado:** comprobar email duplicado, sesión, cookies, o intervención de datos (fusión de usuarios) solo a nivel de base bajo gobernanza, no en esta guía.

## 4. Vendedor (rol `seller`) y su sesión

- El inicio y cierre de sesión usan el mismo mecanismo web que un cliente.  
- La asociación a un “cliente activo” (pedido en nombre de tercero) se hace con `POST` `seller.setclient` y `POST` `seller.removeclient` en el panel, no reemplazando a la autenticación. Ver [roles/01-vendedor-rol-seller.md](../roles/01-vendedor-rol-seller.md).

## 5. Síntomas frecuentes (mesa de ayuda)

| Síntoma | Qué comprobar primero |
|--------|------------------------|
| “Solo veo el formulario B2B, no dónde pongo email” | Está pasando por `GET` `login` que redirige: usar la ruta o pantalla de acceso con contraseña, magic link, o enlace de reset. |
| Bucle a completar Tronex | Validez de email, unicidad, sesión, token de migración, logs de `laravel.log`. |
| 403 en `/api/seller-dashboard` | El usuario no tiene el rol vendedor o el token (si aplica) no acompaña la petición. |
| Código de magic no llega | Cuenta de prueba, Mailgun, dominio de envío, carpeta de spam, throttle. Técnica: [../tecnica/](../tecnica/README.md). |

## 6. Referencias

- [01-vision-general-rutas-y-flujos.md](./01-vision-general-rutas-y-flujos.md) — listado de rutas.  
- [04-carrito-checkout-y-ordenes.md](./04-carrito-checkout-y-ordenes.md) — qué pasa al cerrar un pedido **después** de autenticado.  
- [../admin/10-usuarios-vendedores-y-accesos.md](../admin/10-usuarios-vendedores-y-accesos.md) — 48h, zonas, export.

---

*Revisión: abril 2026. Validar nombres de ruta y middleware tras cada despliegue de ramas grandes.*
