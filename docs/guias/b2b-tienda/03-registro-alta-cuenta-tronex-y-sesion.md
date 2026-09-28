# Registro, alta, Tronex y sesión (Web)

Audiencia: operaciones, soporte y compradores. Referencias: `routes/auth.php`, `routes/web.php`, `TronexMigrationController`, `NewClientController`, `MagicLinkController`.

> **Importante:** `GET login` **redirige** al formulario B2B (`/formulario`). La autenticación por contraseña es el `POST` de login (`AuthenticatedSessionController@store`). El email de login/recuperación es **case-insensitive**.

## 1. Vías hacia un usuario identificado

### A) Formulario B2B (`/formulario`)

- **Rutas:** `form`, `form_post`, `form.check-existing`, `form.cities-by-state`.
- Captura de interesados / inicio de alta; **no** equivale por sí sola a una cuenta lista para comprar con contraseña.

### B) Registro con contraseña (`register` / `complete` en `auth.php`)

- Flujo Breeze: email, contraseña, verificación de email si aplica.

### C) Cliente nuevo / autoservicio (`/cliente-nuevo`)

- **Rutas:** `new-client.create`, `new-client.store`, `new-client.existing-client`.
- Modos de uso (según query/`mode` y rol):
  - **Autoservicio** (`self_service`): el interesado crea cuenta; se exige email real.
  - **Vendedor / sucursal:** alta desde Mi Ruta (`?mode=sucursal&return=mi-ruta`) u otros flujos comerciales.
- Tras el alta, el sistema puede enviar **invitación de registro** (correo) y marcar `must_change_password` → flujo `/cambiar-contrasena`.
- Prospectos / borradores pueden forzar Coordinadora 48h según reglas de negocio del controlador.

### D) Migración Tronex

- `POST /tronex/migrate` → luego `GET`/`POST` `/tronex/completar-perfil` (auth) para email y contraseña definitivos.
- Mientras el perfil de migración esté pendiente, el middleware limita las rutas (completar, guardar, logout).

## 2. Cierre de sesión, recuperación, código mágico, verificación

| Flujo | Rutas | Notas operativas |
|-------|-------|------------------|
| Olvidé la contraseña | `password.request` / `password.email`, `password.reset` / `password.store` | Email case-insensitive; revisar Mailgun/spam si no llega. |
| Código mágico (sin contraseña) | `magic-link.send`, `magic-link.verify` | Código de **6 dígitos** por correo (no solo un enlace). Límites: **3 envíos** y **5 verificaciones** por email cada **5 minutos**. Bloqueado si el usuario debe actualizar email (`requiresClientEmailUpdate`). Supervisor autenticado redirige a Mi Cuenta `tab=mis-rutas`. |
| Verificar email | `verification.notice`, `verification.verify` | Confirmar si la instancia exige email verificado antes de comprar. |

## 3. Procedimiento Tronex (soporte)

1. Migrar (documento / contacto según UI).
2. Si queda autenticado y pendiente → `tronex.completar-perfil`.
3. Completar email único y contraseña.
4. Si se atasca: email duplicado, sesión, logs.

## 4. Vendedor / supervisor

- Misma autenticación web que el cliente.
- Cliente activo: `seller.setclient` / `seller.removeclient`. Ver [roles/01](../roles/01-vendedor-rol-seller.md) y [roles/03 Mi Cuenta](../roles/03-mi-cuenta-pestanas-y-visibilidad.md).

## 5. Síntomas frecuentes

| Síntoma | Qué comprobar primero |
|--------|------------------------|
| “Solo veo el formulario B2B” | `GET login` redirige; usar pantalla de acceso con contraseña, código mágico o reset. |
| Bucle a completar Tronex | Email, unicidad, sesión, logs. |
| Código mágico no llega / “demasiados intentos” | Mailgun, spam, y ventanas de 3/5 por 5 min. |
| No puede entrar por magic y pide actualizar datos | `requiresClientEmailUpdate` — completar actualización de correo antes. |
| 403 en `/api/seller-dashboard` | Rol vendedor/supervisor o auth. |
| Alta por `/cliente-nuevo` sin contraseña usable | Revisar correo de invitación y flujo `must_change_password`. |

## 6. Referencias

- [01 — Visión general de rutas](./01-vision-general-rutas-y-flujos.md)
- [04 — Carrito y órdenes](./04-carrito-checkout-y-ordenes.md)
- [03 — Mi Cuenta](../roles/03-mi-cuenta-pestanas-y-visibilidad.md)
- [10 — Usuarios (admin)](../admin/10-usuarios-vendedores-y-accesos.md)

---

*Revisión: septiembre 2026.*
