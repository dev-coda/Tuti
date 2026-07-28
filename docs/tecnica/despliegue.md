# Guía de despliegue (Tuti)

Describe cómo desplegar Tuti en producción usando el script de despliegue automatizado. Contenido alineado con [DEPLOYMENT.md](../DEPLOYMENT.md) (inglés).

## Inicio rápido

```bash
# En el servidor, dentro del directorio del proyecto
cd /ruta/a/tuti

# Despliegue por defecto (rama `stage`, sin reiniciar servicios)
bash deploy.sh

# Otra rama
bash deploy.sh master

# Despliegue completo con reinicio de servicios
bash deploy.sh --full
bash deploy.sh stage --full
```

## Script `deploy.sh`

### Modo estándar (por defecto)

`bash deploy.sh` o `bash deploy.sh <rama>`

- Activa el **modo mantenimiento** (`php artisan down`)
- Hace `git pull` de la rama indicada
- Ejecuta `composer install`
- Ejecuta **migraciones** (`php artisan migrate`)
- Siembra **tamaños de empaque** Coordinadora (`PackageTypeSeeder`, idempotente)
- Asegura el **enlace simbólico** de almacenamiento (`storage:link`)
- Ajusta **permisos** de `storage` y caché
- **Limpia y regenera caché** (config, rutas, vistas, `optimize`)
- Señala reinicio de colas (`php artisan queue:restart`) para recargar jobs
- **No** hace reinicio duro de Supervisor ni de Nginx/PHP-FPM (usar `--full`)

### Modo completo (`--full` o `--services`)

Añade:

- Reinicio duro de workers de cola (`supervisorctl restart all`)
- Reinicio de servicios web (p. ej. `php8.1-fpm`, `nginx`)

Usar tras cambios en clases de jobs, proveedores, middleware, o problemas de caché; no hace falta en cada publicación de vistas o lógica menor.

Tras el despliegue, validar variables Coordinadora/FV y flags de admin según [coordinadora-48h-stage-checklist.md](../coordinadora-48h-stage-checklist.md) (el script no escribe `.env` ni settings de negocio).

## Configuración previa

### 1. Ejecutable

```bash
chmod +x deploy.sh
```

### 2. Variables de entorno (opcional)

```bash
export PHP_VERSION=8.2
export WEB_USER=nginx
bash deploy.sh
```

### 3. Sudo (para reinicios con `--full`)

Añadir en `sudoers` (ej. `/etc/sudoers.d/deploy`):

```
deploy-user ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart php8.1-fpm
deploy-user ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart nginx
deploy-user ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl restart all
```

## Despliegue manual (sin script)

1. `php artisan down --retry=60`
2. `git pull origin <rama>`
3. `composer install --no-dev --optimize-autoloader`
4. `php artisan migrate --force`
5. `php artisan db:seed --class=PackageTypeSeeder --force`
6. `php artisan storage:link --force`
7. `chmod` / `chown` sobre `storage` y `bootstrap/cache` según el servidor
8. Limpiar: `config:clear`, `cache:clear`, `view:clear`, `route:clear`
9. Regenerar: `config:cache`, `route:cache`, `view:cache`, `optimize`
10. `php artisan queue:restart` (+ `supervisorctl` / PHP-FPM / Nginx si aplica)
11. `php artisan up`

## Incidencias frecuentes

### Imágenes o archivos no aparecen

Enlace de `public/storage` roto:

```bash
php artisan storage:link --force
chmod -R 755 storage/app/public
chown -R www-data:www-data storage/app/public
ls -la public/storage   # debe apuntar a ../storage/app/public
```

### Fallan las migraciones

```bash
php artisan migrate:status
# Si hace falta, rollback de un paso (con cuidado en producción)
php artisan migrate:rollback --step=1
php artisan migrate --force
```

### Errores de permisos

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 755 storage bootstrap/cache
```

### Colas no procesan jobs

```bash
sudo supervisorctl status
sudo supervisorctl restart all
```

Véase también [colas-y-horizon.md](./colas-y-horizon.md).

## Lista de comprobación post-despliegue

- [ ] La aplicación carga sin error
- [ ] Imágenes de productos y subidas a admin
- [ ] Trabajos en cola se consumen
- [ ] Migraciones correctas
- [ ] `tail -f storage/logs/laravel.log` sin errores críticos
- [ ] Registro, catálogo, carrito, creación de pedido y acceso al panel admin básico

## Monitorización básica

```bash
tail -f storage/logs/laravel.log
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/php8.1-fpm.log
sudo supervisorctl status
```

## Rollback (emergencia)

1. Volver a un commit previo: `git reset --hard <hash-anterior>` (o desplegar otra rama/etiqueta)
2. Si aplica, `php artisan migrate:rollback --step=1`
3. Limpiar y recompilar cachés
4. Reiniciar PHP-FPM, Nginx y workers

## CI/CD (ejemplo)

Integración típica: SSH al servidor y ejecutar `bash deploy.sh <rama>`; ver sección *CI/CD* en [DEPLOYMENT.md](../DEPLOYMENT.md) para un ejemplo con GitHub Actions.

## Seguridad

- No commitear `.env`
- Diferenciar entornos (local / stage / producción)
- Desplegar con SSH por clave, no por contraseña
- Limitar `sudo` al usuario de despliegue

---

**Revisado:** Abril 2026
