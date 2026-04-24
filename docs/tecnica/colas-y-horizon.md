# Colas, trabajos en segundo plano y Horizon

Tuti usa el sistema de colas de Laravel para tareas que no deben bloquear la web (p. ej. **sincronización de inventario**). En producción suele usarse **Redis** y **Laravel Horizon**; en entornos simples puede usarse el driver `database` y un worker gestionado por **Supervisor**.

> Referencia detallada en inglés: [README-QUEUE.md](../README-QUEUE.md), [HORIZON-SETUP.md](../HORIZON-SETUP.md), [QUEUE-SETUP-REQUIRED.md](../QUEUE-SETUP-REQUIRED.md).

## Requisitos comunes

1. Tablas `jobs` y `failed_jobs` (migraciones de Laravel):
   ```bash
   php artisan migrate
   ```
2. Variable `QUEUE_CONNECTION` en `.env`:
   - `database` — worker con `php artisan queue:work database` (o Supervisor)
   - `redis` — adecuado con Horizon; requiere **Redis** en marcha

## Probar en local (manual)

Mantener un terminal con:

```bash
php artisan queue:work database --sleep=3 --tries=3
```

## Producción: Supervisor (driver `database`)

1. Instalar Supervisor (p. ej. en Ubuntu: `apt install supervisor`).
2. Añadir un fichero de programa que ejecute `php /ruta/artisan queue:work` con el usuario y rutas correctos. El repositorio puede incluir un ejemplo bajo `docs/scripts/` (ver [README-QUEUE.md](../README-QUEUE.md)).
3. `supervisorctl reread`, `update` y comprobar `status`.

## Producción: Redis y Horizon

1. Arrancar Redis (`redis-cli ping` → `PONG`).
2. En `.env`: `QUEUE_CONNECTION=redis` (y ajustar `REDIS_*` según el servidor).
3. `php artisan horizon` o servicio bajo Supervisor para **Horizon**; la UI suele publicarse bajo ruta de administración según [HORIZON-SETUP.md](../HORIZON-SETUP.md).

Tras cambiar configuración: limpiar caché y reiniciar workers:

```bash
php artisan config:clear
php artisan config:cache
# Reiniciar Horizon o Supervisor
```

## Sincronización de inventario

El inventario se sincroniza de forma **asíncrona** (no bloquea la petición web). Si las colas no se procesan, las sincronizaciones y otros jobs se acumularán; revisar `failed_jobs` y logs de Laravel.

## Recomendación tras un despliegue

- Modo despliegue **estándar** (`deploy.sh` sin `--full`): a veces no se reinician workers. Si se desplegó código de jobs, usar `deploy.sh --full` o reiniciar workers manualmente.
- Ver sección *Queue Workers* en [despliegue.md](./despliegue.md).

---

**Revisado:** Abril 2026
