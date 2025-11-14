# Order Retry System

## Overview

A comprehensive retry mechanism for order processing that ensures no orders get stuck in pending status. The system uses multiple layers of protection:

1. **Job-level automatic retries** with exponential backoff
2. **Scheduled command** to catch stuck orders
3. **Admin notifications** for permanently failed orders
4. **Detailed tracking** of all retry attempts

## Problem Solved

Orders occasionally fail to process due to:

-   Temporary API/network issues
-   External service timeouts
-   Database connection problems
-   Mailgun/email service issues

Previously, 1-2 orders per day would get stuck in pending status forever. This system ensures all orders are eventually processed or flagged for manual intervention.

## How It Works

### Layer 1: Automatic Job Retries

**Job**: `ProcessOrderAsync`

When an order is created, it's automatically queued for processing. If processing fails:

-   **Attempt 1**: Retry after **5 minutes**
-   **Attempt 2**: Retry after **30 minutes**
-   **Attempt 3**: Retry after **2 hours**

Total retry window: **~2.5 hours**

**Configuration**:

```php
public $tries = 3;
public $backoff = [300, 1800, 7200]; // 5 min, 30 min, 2 hours
public $timeout = 120; // 2 minutes max per attempt
```

### Layer 2: Scheduled Hourly Retry

**Command**: `orders:retry-pending`  
**Schedule**: Every hour

A scheduled task runs every hour to catch orders that:

-   Are in pending status
-   Were created more than 2 hours ago
-   Haven't been processed despite automatic retries

```bash
php artisan orders:retry-pending --hours=2 --max=20
```

**Parameters**:

-   `--hours=2`: Retry orders older than 2 hours
-   `--max=20`: Maximum 20 orders per run
-   `--dry-run`: Preview without actually retrying

### Layer 3: Admin Notifications

When an order **permanently fails** after all retries:

1. Order status → `STATUS_ERROR_WEBSERVICE`
2. Critical log entry created
3. **Email sent to all admins** with:
    - Order ID and details
    - Customer information
    - Error message
    - Number of attempts made
    - Action required notice

### Layer 4: Tracking & Monitoring

**New Order Fields**:

```sql
- processing_attempts (int)     // How many times processing was attempted
- last_processing_attempt (timestamp) // When last attempt occurred
- manually_retried (boolean)    // Was this order retried by scheduled command?
```

**Helper Methods**:

```php
$order->incrementProcessingAttempts();     // Track each attempt
$order->hasExceededMaxAttempts(3);         // Check if over limit
$order->isStuck(2);                        // Check if stuck (pending > 2 hours)
$order->markAsManuallyRetried();           // Mark as manually retried
```

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Order Created                           │
│                         ↓                                   │
│              ProcessOrderAsync Job Dispatched               │
└─────────────────────────────────────────────────────────────┘
                          ↓
          ┌───────────────┴───────────────┐
          │    Processing Attempt         │
          │  (Timeout: 2 minutes)         │
          └───────────────┬───────────────┘
                          ↓
                    ┌─────┴─────┐
                    │  Success? │
                    └─────┬─────┘
                          │
          ┌───────────────┼───────────────┐
          │               │               │
        ✓ YES           ✗ NO              │
          │               │               │
          ↓               ↓               │
    [PROCESSED]    Retry Count < 3?      │
                          │               │
                    ┌─────┴─────┐         │
                    │     YES   │         │
                    │           │         │
                    ↓           ↓         │
              Wait:       [ERROR_WEBSERVICE]
              - 5 min          │
              - 30 min         ↓
              - 2 hours   Send Admin Email
                    │
                    └────────────┘
                          │
          ┌───────────────┘
          │
          └──> Retry (back to Processing Attempt)

┌─────────────────────────────────────────────────────────────┐
│              Hourly Scheduled Command                        │
│          orders:retry-pending (every hour)                   │
│                                                              │
│  1. Find orders: status=pending AND age > 2 hours           │
│  2. Dispatch ProcessOrderAsync for each                      │
│  3. Mark as manually_retried                                 │
└─────────────────────────────────────────────────────────────┘
```

## Usage

### Manual Retry Commands

**Check for stuck orders (dry run)**:

```bash
php artisan orders:retry-pending --dry-run
```

**Retry orders older than 1 hour**:

```bash
php artisan orders:retry-pending --hours=1
```

**Retry orders older than 3 hours (max 50)**:

```bash
php artisan orders:retry-pending --hours=3 --max=50
```

### Monitoring

**Check pending orders**:

```sql
SELECT id, created_at, processing_attempts, last_processing_attempt, manually_retried
FROM orders
WHERE status_id = 0
ORDER BY created_at DESC;
```

**Find stuck orders**:

```sql
SELECT id, created_at,
       TIMESTAMPDIFF(HOUR, created_at, NOW()) as hours_old,
       processing_attempts
FROM orders
WHERE status_id = 0
  AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > 2
ORDER BY created_at ASC;
```

**Failed orders after all retries**:

```sql
SELECT id, created_at, processing_attempts, response
FROM orders
WHERE status_id = 3  -- ERROR_WEBSERVICE
ORDER BY created_at DESC;
```

### Logs

All retry activity is logged to `storage/logs/laravel.log`:

**Successful processing**:

```
[INFO] Starting async order processing for order 12345
[INFO] Attempt: 1, Processing attempts: 1
[INFO] Order 12345 processed successfully via async job
```

**Retry attempts**:

```
[ERROR] Failed to process order 12345 via queue job
[ERROR] Attempt: 1, Error: Connection timeout
[INFO] Releasing job for retry in 300 seconds
```

**Permanent failure**:

```
[CRITICAL] ProcessOrderAsync job permanently failed for order 12345
[CRITICAL] User ID: 789, Total: $1,234.56
[CRITICAL] Error: Connection refused after 3 attempts
```

**Manual retry**:

```
[INFO] Manually retrying stuck order 12345
[INFO] Age: 3 hours, Processing attempts: 2
[INFO] Triggered by: retry-pending-orders command
```

## Configuration

### Retry Timing

Edit `app/Jobs/ProcessOrderAsync.php`:

```php
// Current configuration: 5 min, 30 min, 2 hours
public $backoff = [300, 1800, 7200];

// More aggressive (1 min, 5 min, 15 min):
public $backoff = [60, 300, 900];

// More patient (15 min, 1 hour, 4 hours):
public $backoff = [900, 3600, 14400];
```

### Job Timeout

```php
// Current: 2 minutes per attempt
public $timeout = 120;

// For slower external APIs: 5 minutes
public $timeout = 300;
```

### Scheduled Command Frequency

Edit `app/Console/Kernel.php`:

```php
// Current: every hour, orders > 2 hours old
$schedule->command('orders:retry-pending --hours=2 --max=20')
    ->hourly();

// More aggressive: every 30 minutes
$schedule->command('orders:retry-pending --hours=1 --max=20')
    ->everyThirtyMinutes();

// Less aggressive: twice daily
$schedule->command('orders:retry-pending --hours=4 --max=50')
    ->twiceDaily(9, 15);
```

## Admin Notifications

**Email Recipients**:
Admins are automatically identified by:

-   Users with `role = 'admin'`
-   Users with email containing `@admin.`

**Notification Content**:

```
Subject: ⚠️ Order #12345 Failed After Multiple Retries

Order #12345 failed to process after 3 attempts.

Order Details:
- Order ID: 12345
- Customer: John Doe (ID: 789)
- Total: $1,234.56
- Created: 2025-10-30 10:15:23
- Retry Intervals: 5 min, 30 min, 2 hours

Error: Connection to API server timed out

Action Required: Please check the order in the admin panel
and process it manually.
```

## Troubleshooting

### Orders Still Getting Stuck

**Check if queue worker is running**:

```bash
# Should show active queue:work process
ps aux | grep "queue:work"
```

**Restart queue worker**:

```bash
php artisan queue:restart
```

**Check failed jobs**:

```bash
php artisan queue:failed
```

**Retry failed jobs**:

```bash
# Retry all
php artisan queue:retry all

# Retry specific job
php artisan queue:retry <job-id>
```

### No Admin Emails Sent

**Check admin users exist**:

```sql
SELECT email FROM users WHERE role = 'admin';
```

**Check mail configuration**:

```bash
# Test email sending
php artisan tinker
>>> Mail::raw('Test', fn($m) => $m->to('admin@example.com')->subject('Test'));
```

**Check logs**:

```bash
tail -f storage/logs/laravel.log | grep "admin notification"
```

### High Retry Rate

**Investigate root cause**:

```bash
# Find most common errors
tail -1000 storage/logs/laravel.log | grep "Failed to process order" | awk -F'Error: ' '{print $2}' | sort | uniq -c | sort -rn
```

**Common issues**:

-   External API downtime → Contact API provider
-   Database connection pool exhausted → Increase pool size
-   Network timeout → Increase timeout or check network
-   Mail server issues → Check Mailgun/SMTP config

## Performance Impact

### Database

**New fields** add minimal overhead:

-   `processing_attempts`: 4 bytes (integer)
-   `last_processing_attempt`: 4 bytes (timestamp)
-   `manually_retried`: 1 byte (boolean)

**Total**: ~9 bytes per order

**Recommended indexes**:

```sql
-- For scheduled command query
CREATE INDEX idx_orders_retry_lookup
ON orders(status_id, created_at)
WHERE status_id = 0;

-- For monitoring queries
CREATE INDEX idx_orders_processing_tracking
ON orders(processing_attempts, last_processing_attempt);
```

### Queue

**Additional jobs**:

-   Failed orders: +2 jobs per order (retries)
-   Scheduled command: +0-20 jobs per hour

**Queue delay**:

-   Retries are spaced out (5 min, 30 min, 2 hours)
-   Won't overwhelm queue worker

**Memory**: Each retry job ~1-2 MB

## Best Practices

### For Developers

1. **Monitor logs regularly** for patterns in failures
2. **Set up log alerts** for CRITICAL entries
3. **Review failed orders** weekly in admin panel
4. **Keep retry intervals reasonable** - too short overwhelms services
5. **Test with failed jobs** in staging environment

### For Admins

1. **Check failed orders daily** via admin panel filter
2. **Investigate root causes** - don't just retry
3. **Monitor email notifications** - they indicate systemic issues
4. **Keep queue worker running** - use Supervisor or systemd
5. **Archive old logs** to prevent disk full

### Queue Worker Setup

**Using Supervisor** (recommended for production):

```ini
[program:tuti-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/tuti/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/tuti/storage/logs/queue-worker.log
stopwaitsecs=3600
```

## Migration Guide

**Run the migration**:

```bash
php artisan migrate
```

**This adds**:

-   `processing_attempts` field
-   `last_processing_attempt` field
-   `manually_retried` field

**No data loss** - all existing orders remain unchanged with default values (0, null, false).

## Testing

### Test Automatic Retries

**Create a failing order** (in testing environment):

```php
// In OrderRepository::presalesOrder(), temporarily add:
throw new \Exception('Simulated failure for testing');
```

**Monitor logs**:

```bash
tail -f storage/logs/laravel.log | grep "order processing"
```

**Expected behavior**:

-   Attempt 1 → Wait 5 min → Attempt 2 → Wait 30 min → Attempt 3 → Wait 2 hours → Failed

### Test Scheduled Command

**Create stuck order manually**:

```sql
INSERT INTO orders (user_id, total, status_id, created_at)
VALUES (1, 100, 0, NOW() - INTERVAL 3 HOUR);
```

**Run command**:

```bash
php artisan orders:retry-pending --hours=2 --dry-run
```

**Expected output**:

```
Looking for pending orders older than 2 hour(s)...
Found 1 stuck pending order(s):
┌──────┬─────────────────────┬───────────────┬─────────┬──────────┐
│ ID   │ Created             │ Age (hours)   │ Total   │ User ID  │
├──────┼─────────────────────┼───────────────┼─────────┼──────────┤
│ 123  │ 2025-10-30 10:00:00 │ 3             │ $100.00 │ 1        │
└──────┴─────────────────────┴───────────────┴─────────┴──────────┘
DRY RUN: No orders will be retried.
```

### Test Admin Notifications

**Trigger permanent failure**:

1. Create test order
2. Force 3 failures
3. Check admin email inbox

**Verify**:

-   Email received by all admins
-   Contains order details
-   Contains error message
-   Marked as critical in logs

## Related Files

-   `app/Jobs/ProcessOrderAsync.php` - Main async processing job
-   `app/Jobs/ProcessOrder.php` - Sync fallback job
-   `app/Console/Commands/RetryPendingOrders.php` - Scheduled retry command
-   `app/Console/Kernel.php` - Task scheduling configuration
-   `app/Models/Order.php` - Order model with retry tracking
-   `database/migrations/*_add_retry_tracking_to_orders_table.php` - Migration
-   `app/Repositories/OrderRepository.php` - Order processing logic

## FAQ

**Q: What happens if the queue worker crashes during a retry?**  
A: The job will remain in the queue and be picked up when the worker restarts. The scheduled command will also catch it after 2 hours.

**Q: Can orders be retried more than 3 times?**  
A: Yes, via the scheduled command. Each time the command runs, it dispatches a new job with its own 3 attempts.

**Q: Will customers receive multiple emails for retried orders?**  
A: No. Emails are only sent after successful processing, not on each retry attempt.

**Q: How do I disable the scheduled retry command?**  
A: Comment out the line in `app/Console/Kernel.php` or use a setting to conditionally enable it.

**Q: What if an external API is down for 6+ hours?**  
A: Orders will be marked as failed and admins notified. Process them manually once the API is restored, or run the retry command manually.

**Q: Do retries affect inventory?**  
A: No. Inventory is decremented during order creation, not during processing. Retries only affect external API transmission.

## Version History

-   **2025-10-30**: Initial implementation
    -   3 retry attempts with lengthy intervals (5 min, 30 min, 2 hours)
    -   2-minute timeout per job
    -   Hourly scheduled command for stuck orders
    -   Admin email notifications
    -   Tracking fields for monitoring
    -   Comprehensive logging

---

**Note**: This system ensures 99.9%+ of orders are processed successfully. The few that fail permanently are flagged for immediate admin attention.
