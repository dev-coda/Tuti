# ⚠️ QUEUE CONFIGURATION REQUIRED FOR INVENTORY SYNC

## ✨ Recommended: Use Horizon (Like Master)

**Master branch uses Laravel Horizon with Redis** for queue management. This is the recommended setup.

👉 **See [HORIZON-SETUP.md](docs/HORIZON-SETUP.md) for complete Horizon setup instructions.**

---

## Problem

The inventory sync feature requires **asynchronous job processing** to avoid PHP execution timeouts. If you're seeing this error:

```
Fatal error: Maximum execution time of 30+2 seconds exceeded
in vendor/guzzlehttp/guzzle/src/Handler/CurlHandler.php
```

It means your queue is configured to run **synchronously** instead of **asynchronously**.

## Solution

### 1. Update .env File

Change your `.env` file from:

```env
QUEUE_CONNECTION=sync
```

To:

```env
QUEUE_CONNECTION=database
```

### 2. Run Migrations

Ensure the jobs table exists:

```bash
php artisan migrate
```

This will create the `jobs` and `failed_jobs` tables if they don't exist.

### 3. Start Queue Worker

**For Development:**

```bash
php artisan queue:work database --sleep=3 --tries=3
```

Keep this terminal open. The worker will process jobs as they arrive.

**For Production (using Supervisor):**

See `docs/README-QUEUE.md` for complete supervisor setup instructions.

## Why This is Required

The inventory sync:

-   Fetches data from multiple bodegas (warehouses)
-   Makes SOAP API calls that can take 20-30 seconds each
-   Processes and updates hundreds of products
-   **Total time can exceed 5 minutes**

When `QUEUE_CONNECTION=sync`:

-   Jobs run in the same web request
-   Hit PHP's 30-second execution limit
-   Cause timeout errors

When `QUEUE_CONNECTION=database` + queue worker running:

-   Jobs run in background process
-   Have their own execution time limits (300 seconds configured)
-   Don't block web requests
-   Return immediately to user

## Verify Queue is Working

1. Check if queue worker is running:

```bash
ps aux | grep "queue:work"
```

2. Check pending jobs:

```bash
php artisan queue:failed
```

3. Monitor jobs table:

```bash
php artisan tinker
>>> DB::table('jobs')->count()
```

## Master Branch Configuration

The master branch works because it has:

-   ✅ `QUEUE_CONNECTION=database` in .env
-   ✅ Queue worker running in background
-   ✅ Jobs processed asynchronously

## Quick Fix Checklist

-   [ ] Changed `QUEUE_CONNECTION=database` in .env
-   [ ] Ran `php artisan migrate`
-   [ ] Started queue worker: `php artisan queue:work database`
-   [ ] Tested inventory sync - should return immediately with "iniciada" message
-   [ ] Verified jobs are being processed in background
