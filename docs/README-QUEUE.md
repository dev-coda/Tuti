# Queue Worker Setup for Inventory Sync

The inventory sync now runs asynchronously using Laravel queues. This means the sync process runs in the background and doesn't block the web interface.

## Prerequisites

1. Make sure the `jobs` and `failed_jobs` tables exist in your database:

    ```bash
    php artisan migrate
    ```

2. Update your `.env` file to use the database queue:
    ```env
    QUEUE_CONNECTION=database
    ```

## Running the Queue Worker

You have several options to run the queue worker:

### Option 1: Manual (for testing)

Simply run this command in a terminal:

```bash
php artisan queue:work database --sleep=3 --tries=3
```

Keep this terminal open. The worker will process jobs as they come in.

### Option 2: Using Supervisor (recommended for production)

1. Install Supervisor:

    ```bash
    # macOS
    brew install supervisor
    brew services start supervisor

    # Ubuntu/Debian
    sudo apt-get install supervisor
    sudo systemctl enable supervisor
    sudo systemctl start supervisor
    ```

2. Copy the supervisor config:

    ```bash
    sudo cp docs/scripts/supervisor-queue-worker.conf /usr/local/etc/supervisor.d/tuti-queue-worker.conf
    # or on Linux:
    sudo cp docs/scripts/supervisor-queue-worker.conf /etc/supervisor/conf.d/tuti-queue-worker.conf
    ```

3. Update the config file paths if needed:

    - Edit the `command=` line to use the correct path to your artisan file
    - Edit the `stdout_logfile=` to use the correct log path
    - Update the `user=` to your system user

4. Reload supervisor:

    ```bash
    # macOS
    brew services restart supervisor
    supervisorctl reread
    supervisorctl update
    supervisorctl start tuti-queue-worker:*

    # Linux
    sudo supervisorctl reread
    sudo supervisorctl update
    sudo supervisorctl start tuti-queue-worker:*
    ```

5. Check the status:
    ```bash
    supervisorctl status
    ```

### Option 3: Using systemd (Linux alternative)

Create a systemd service file at `/etc/systemd/system/tuti-queue-worker.service`:

```ini
[Unit]
Description=Tuti Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/Tuti
ExecStart=/usr/bin/php /path/to/Tuti/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

Then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable tuti-queue-worker
sudo systemctl start tuti-queue-worker
sudo systemctl status tuti-queue-worker
```

## Monitoring

-   Check queue worker logs: `tail -f storage/logs/queue-worker.log`
-   Check Laravel logs: `tail -f storage/logs/laravel.log`
-   Monitor failed jobs: `php artisan queue:failed`
-   Retry failed jobs: `php artisan queue:retry all`

## How It Works

1. User clicks "Sincronizar Inventario" button
2. Job is added to the `jobs` table
3. Queue worker picks up the job and processes it in the background
4. Job updates the `inventory_last_synced_at` setting when complete
5. User can refresh the page to see the updated sync time

## Troubleshooting

### Queue worker not processing jobs?

```bash
# Check if worker is running
ps aux | grep "queue:work"

# Check database for pending jobs
php artisan tinker
>>> DB::table('jobs')->count()
```

### Jobs failing?

```bash
# See failed jobs
php artisan queue:failed

# Retry specific job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Restart queue worker after code changes

```bash
# Using supervisor
supervisorctl restart tuti-queue-worker:*

# Using systemd
sudo systemctl restart tuti-queue-worker

# Manual
# Just stop (Ctrl+C) and restart the command
```
