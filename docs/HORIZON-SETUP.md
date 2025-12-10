# 🚀 Laravel Horizon Setup for Stage Environment

## Overview

This guide will help you set up Laravel Horizon with Redis for queue management on the stage environment, matching the master configuration.

## Prerequisites

✅ **Already Configured:**
- Laravel Horizon package installed (`laravel/horizon: ^5.15`)
- `HorizonServiceProvider` registered in `config/app.php`
- `config/horizon.php` configuration file present
- Redis connection settings in `.env`

## Setup Steps

### 1. Update Environment Configuration

Update your `.env` file with the following changes:

```bash
# Change from:
QUEUE_CONNECTION=sync

# To:
QUEUE_CONNECTION=redis
```

### 2. Verify Redis is Running

Check if Redis server is running:

```bash
# Check Redis status
redis-cli ping
# Should return: PONG

# Or check if Redis process is running
ps aux | grep redis-server
```

If Redis is not running:

**macOS (Homebrew):**
```bash
brew services start redis
```

**Ubuntu/Debian:**
```bash
sudo systemctl start redis-server
sudo systemctl enable redis-server
```

**Docker:**
```bash
docker run -d -p 6379:6379 redis:alpine
```

### 3. Clear and Optimize Configuration

```bash
php artisan config:clear
php artisan cache:clear
php artisan horizon:install
php artisan horizon:publish
```

### 4. Run Horizon

**Development/Testing:**
```bash
php artisan horizon
```

**Production (using Supervisor):**

Create supervisor configuration file `/etc/supervisor/conf.d/horizon.conf`:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /path/to/your/project/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/horizon.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
```

### 5. Access Horizon Dashboard

Once Horizon is running, access the dashboard at:

```
http://your-domain/horizon
```

**Note:** By default, Horizon is only accessible in local environment. For production/stage access, update `app/Providers/HorizonServiceProvider.php`:

```php
protected function gate()
{
    Gate::define('viewHorizon', function ($user) {
        return in_array($user->email, [
            'admin@example.com', // Add your admin emails
        ]);
    });
}
```

## Configuration Details

### Queue Connection

The stage environment will now use Redis for queue management:

**Before:**
```env
QUEUE_CONNECTION=sync  # Jobs run synchronously in web request
```

**After:**
```env
QUEUE_CONNECTION=redis  # Jobs run asynchronously via Horizon
```

### Redis Configuration

Your current Redis settings (already configured):
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Horizon Configuration

Located in `config/horizon.php`, key settings:

```php
'use' => 'default',  // Redis connection to use
'prefix' => env('HORIZON_PREFIX', 'horizon:'),
'path' => env('HORIZON_PATH', 'horizon'),
```

## Benefits of Horizon

✅ **Better than basic queue workers:**
- Beautiful dashboard for monitoring queues
- Real-time metrics and throughput graphs
- Failed job management with retry capabilities
- Auto-scaling workers based on load
- Job tagging for better organization

✅ **Fixes the inventory sync timeout:**
- Jobs run in background with Redis
- No 30-second PHP execution limit
- Better performance and reliability
- Can handle multiple concurrent jobs

## Monitoring

### Check Horizon Status
```bash
php artisan horizon:status
```

### View Queue Stats
```bash
php artisan horizon:list
```

### Monitor Failed Jobs
Access `/horizon/failed` in your browser

### Clear Failed Jobs
```bash
php artisan horizon:clear
```

## Troubleshooting

### Horizon not processing jobs?

1. **Check Horizon is running:**
```bash
ps aux | grep horizon
```

2. **Check Redis connection:**
```bash
php artisan tinker
>>> Redis::ping()
```

3. **Restart Horizon:**
```bash
php artisan horizon:terminate
php artisan horizon
```

### Jobs failing?

View failed jobs in Horizon dashboard at `/horizon/failed`

Or via command:
```bash
php artisan horizon:failed
```

### After code changes

Always restart Horizon:
```bash
php artisan horizon:terminate
# Supervisor will auto-restart, or manually:
php artisan horizon
```

## Comparison: Master vs Stage

| Feature | Master (Working) | Stage (Before) | Stage (After) |
|---------|------------------|----------------|---------------|
| Queue Driver | Redis | Sync | Redis |
| Queue Worker | Horizon | None | Horizon |
| Background Jobs | ✅ Yes | ❌ No | ✅ Yes |
| Inventory Sync | ✅ Works | ❌ Timeout | ✅ Works |
| Monitoring | ✅ Dashboard | ❌ None | ✅ Dashboard |

## Quick Start Checklist

- [ ] Update `.env`: `QUEUE_CONNECTION=redis`
- [ ] Verify Redis is running: `redis-cli ping`
- [ ] Clear config cache: `php artisan config:clear`
- [ ] Start Horizon: `php artisan horizon`
- [ ] Test inventory sync - should work without timeout!
- [ ] Access dashboard: `http://your-domain/horizon`
- [ ] Set up Supervisor for production (optional)

## Notes

- Horizon will automatically create necessary Redis keys
- Failed jobs are automatically retried based on configuration
- Horizon provides better debugging tools than basic queue workers
- All queue:* artisan commands work with Horizon

## Additional Resources

- [Laravel Horizon Documentation](https://laravel.com/docs/horizon)
- [Redis Documentation](https://redis.io/docs/)
- Existing `docs/README-QUEUE.md` for basic queue setup

