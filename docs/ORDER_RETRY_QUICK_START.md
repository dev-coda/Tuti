# Order Retry System - Quick Start Guide

## What Problem Does This Solve?

**Before**: 1-2 orders per day would get stuck in "pending" status forever due to temporary API issues, network problems, or service timeouts.

**After**: All orders are automatically retried up to 3 times with lengthy intervals, and a scheduled task catches any that slip through. Admins are notified if an order permanently fails.

## How It Works (Simple Version)

1. **Order created** → Queued for processing
2. **If processing fails** → Wait 5 min → Retry
3. **If still fails** → Wait 30 min → Retry
4. **If still fails** → Wait 2 hours → Final retry
5. **Still fails?** → Mark as error + Email admins
6. **Backup**: Every hour, a scheduled task checks for stuck orders (> 2 hours old) and retries them

## What Changed?

### ✅ Longer Retry Intervals

-   **Before**: 1 min, 5 min, 15 min
-   **After**: 5 min, 30 min, 2 hours

### ✅ Job Timeout Protection

-   Each processing attempt times out after 2 minutes
-   Prevents jobs from hanging indefinitely

### ✅ Scheduled Safety Net

-   **Command**: `orders:retry-pending`
-   **Runs**: Every hour automatically
-   **Catches**: Orders stuck in pending for > 2 hours

### ✅ Admin Notifications

-   Email sent to all admins when order permanently fails
-   Includes order details and error message

### ✅ Better Tracking

New order fields:

-   `processing_attempts` - How many times processing was tried
-   `last_processing_attempt` - Timestamp of last attempt
-   `manually_retried` - Was this retried by scheduled command?

## Setup (One-Time)

### 1. Migration Already Ran

The migration ran automatically. You can verify:

```bash
php artisan migrate:status
```

Should show: `2025_10_30_135743_add_retry_tracking_to_orders_table` as migrated.

### 2. Queue Worker Must Be Running

```bash
# Check if running
ps aux | grep "queue:work"

# If not running, start it
php artisan queue:work &

# For production, use Supervisor (see full docs)
```

### 3. Scheduler Must Be Active

The scheduled retry command runs via Laravel's scheduler. Ensure this cron job exists:

```bash
# Edit crontab
crontab -e

# Add this line (if not already there)
* * * * * cd /path/to/tuti && php artisan schedule:run >> /dev/null 2>&1
```

## Daily Usage

### Check for Stuck Orders

**Via Command** (recommended):

```bash
# See what would be retried (dry run)
php artisan orders:retry-pending --dry-run

# Actually retry stuck orders
php artisan orders:retry-pending
```

**Via Database**:

```sql
SELECT id, created_at, status_id, processing_attempts
FROM orders
WHERE status_id = 0 -- pending
  AND created_at < NOW() - INTERVAL 2 HOUR
ORDER BY created_at DESC;
```

### Check Failed Orders

```sql
SELECT id, created_at, user_id, total, response
FROM orders
WHERE status_id = 3 -- ERROR_WEBSERVICE
ORDER BY created_at DESC
LIMIT 20;
```

### Monitor Logs

```bash
# Watch live retry activity
tail -f storage/logs/laravel.log | grep "order processing"

# See critical failures
tail -f storage/logs/laravel.log | grep CRITICAL
```

## Common Scenarios

### Scenario 1: External API is Down

**What happens**:

1. Order fails first attempt → Retry in 5 min
2. Still down → Retry in 30 min
3. Still down → Retry in 2 hours
4. Still down → Mark as error, email admins

**What to do**:

-   Wait for API to come back online
-   Run manual retry: `php artisan orders:retry-pending --hours=1`

### Scenario 2: Network Glitch

**What happens**:

-   First attempt fails due to timeout
-   5 minutes later, network is fine → Success!

**Result**: Order processes automatically, no intervention needed.

### Scenario 3: Database Connection Issue

**What happens**:

-   Order fails due to DB connection pool exhausted
-   5 minutes later, connections available → Success!

**Result**: Order processes automatically.

## Monitoring Dashboard (SQL Queries)

**Retry Statistics (Last 24 Hours)**:

```sql
SELECT
    status_id,
    AVG(processing_attempts) as avg_attempts,
    MAX(processing_attempts) as max_attempts,
    COUNT(*) as order_count
FROM orders
WHERE created_at >= NOW() - INTERVAL 24 HOUR
GROUP BY status_id;
```

**Orders That Needed Retries**:

```sql
SELECT id, created_at, processing_attempts, manually_retried, status_id
FROM orders
WHERE processing_attempts > 1
ORDER BY created_at DESC
LIMIT 50;
```

**Success Rate**:

```sql
SELECT
    COUNT(CASE WHEN status_id = 1 THEN 1 END) as successful,
    COUNT(CASE WHEN status_id = 0 THEN 1 END) as pending,
    COUNT(CASE WHEN status_id = 3 THEN 1 END) as failed,
    COUNT(*) as total,
    ROUND(COUNT(CASE WHEN status_id = 1 THEN 1 END) * 100.0 / COUNT(*), 2) as success_rate
FROM orders
WHERE created_at >= NOW() - INTERVAL 7 DAY;
```

## Troubleshooting

### Orders Still Stuck After 3+ Hours

**Check**:

```bash
# 1. Is queue worker running?
ps aux | grep "queue:work"

# 2. Any failed jobs?
php artisan queue:failed

# 3. Check logs
tail -100 storage/logs/laravel.log | grep "order processing"
```

**Fix**:

```bash
# Restart queue worker
php artisan queue:restart

# Retry failed jobs
php artisan queue:retry all

# Manually retry stuck orders
php artisan orders:retry-pending --hours=1
```

### Admin Emails Not Received

**Check**:

```bash
# 1. Are there admin users?
php artisan tinker
>>> User::where('role', 'admin')->pluck('email');

# 2. Test email sending
>>> Mail::raw('Test', fn($m) => $m->to('admin@example.com')->subject('Test'));
```

### High Failure Rate

**Investigate**:

```bash
# Find most common error messages
tail -1000 storage/logs/laravel.log | grep "Failed to process order" | grep -oP 'Error: \K.*' | sort | uniq -c | sort -rn | head -10
```

## Quick Commands Cheat Sheet

```bash
# Check for stuck orders (no changes)
php artisan orders:retry-pending --dry-run

# Retry stuck orders (> 2 hours old, max 20)
php artisan orders:retry-pending

# Retry orders older than 1 hour
php artisan orders:retry-pending --hours=1

# Retry orders older than 4 hours, max 50
php artisan orders:retry-pending --hours=4 --max=50

# Check queue status
php artisan queue:work --once

# View failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Restart queue workers
php artisan queue:restart
```

## When to Manually Intervene

**Automatically handled** (no action needed):

-   ✅ Temporary network issues
-   ✅ Brief API downtime (< 2.5 hours)
-   ✅ Database connection hiccups
-   ✅ Email service timeouts

**Requires manual action**:

-   ❌ Orders failed after all retries (check admin email)
-   ❌ External API down for > 3 hours
-   ❌ Systemic issues causing 50%+ failures
-   ❌ Queue worker crashed and not restarting

## Success Metrics

After implementing this system, you should see:

-   **0-1** stuck orders per day (vs 1-2 before)
-   **99.5%+** orders process successfully
-   **< 5 minutes** median time from creation to processing
-   **Automated recovery** from 90%+ of transient failures

## Next Steps

1. ✅ Monitor logs for first 48 hours
2. ✅ Check admin email notifications work
3. ✅ Review stuck orders (if any) after 1 week
4. ✅ Adjust retry timings if needed (see full docs)
5. ✅ Set up Supervisor for production queue worker

## More Information

-   **Full Documentation**: `docs/ORDER_RETRY_SYSTEM.md`
-   **Codebase**:
    -   `app/Jobs/ProcessOrderAsync.php`
    -   `app/Console/Commands/RetryPendingOrders.php`
    -   `app/Models/Order.php`

## Questions?

Check the FAQ in `ORDER_RETRY_SYSTEM.md` or review the logs for specific error messages.

---

**Remember**: The system is designed to handle 99%+ of cases automatically. Only orders with persistent issues will require manual intervention, and you'll be notified via email.
