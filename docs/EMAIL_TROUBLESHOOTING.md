# Email Troubleshooting Guide - Automatic Emails Not Firing

## Issue
- Test emails work ✅
- Horizon queue is processing jobs ✅
- But automatic order emails are NOT being sent ❌

## Root Causes

### 1. Missing Order Relationships (MOST COMMON - FIXED November 2025)
The `ProcessOrderAsync` job was refreshing the order from the database without eager-loading the necessary relationships (`products.product`, `user`, `zone`). This caused the email service to fail silently when trying to access `$order->products->product->name`.

**Fix Applied:** The job now properly loads all relationships before sending emails.

### 2. Email Templates Missing or Inactive
Email templates may be missing or inactive in the production database.

## How to Diagnose

### Step 1: Check Production Logs

SSH into your production server and check the Laravel logs:

```bash
tail -f storage/logs/laravel.log | grep -i email
```

Look for these warning messages:
```
Email template not found or inactive: order_confirmation
Email template not found or inactive: order_status_processed
```

If you see these warnings, **the templates are missing or inactive**.

### Step 2: Check Email Templates in Database

Connect to your production database and run:

```sql
-- Check if email templates exist
SELECT slug, is_active, name FROM email_templates;

-- Check for order-related templates specifically
SELECT slug, is_active, name 
FROM email_templates 
WHERE slug IN ('order_confirmation', 'order_status_pending', 'order_status_processed');
```

**Expected results:**
- `order_confirmation` with `is_active = 1`
- `order_status_pending` with `is_active = 1`
- `order_status_processed` with `is_active = 1`
- `order_status_shipped` with `is_active = 1`
- `order_status_delivered` with `is_active = 1`
- `order_status_cancelled` with `is_active = 1`

### Step 3: Check Horizon Logs

In your browser, go to:
```
https://your-production-url.com/horizon
```

Check:
1. **Recent Jobs** - Look for `ProcessOrderAsync` jobs
2. **Failed Jobs** - Check if any email jobs failed
3. Click on a completed job to see detailed logs

## Solutions

### Solution 1: Seed Email Templates (Recommended)

If templates are missing, run the seeder in production:

```bash
php artisan db:seed --class=EmailTemplatesSeeder
```

This will create all the necessary email templates with default content.

### Solution 2: Activate Templates Manually

If templates exist but are inactive:

```sql
UPDATE email_templates SET is_active = 1 
WHERE slug LIKE 'order_%';
```

### Solution 3: Create Templates via Admin Panel

1. Log in to admin panel
2. Go to Email Templates section
3. Create the following templates:

#### Required Templates:

**1. Order Confirmation** (`order_confirmation`)
- **Slug:** `order_confirmation`
- **Type:** Order Confirmation
- **Active:** ✓ Yes
- **Subject:** `Confirmación de Pedido #{order_id}`
- **Body:**
```
Hola {customer_name},

Gracias por tu pedido #{order_id}.

Total: ${order_total}
Fecha de entrega estimada: {delivery_date}

Puedes ver tu pedido en: {order_url}

Gracias por tu compra!
```

**2. Order Status - Processed** (`order_status_processed`)
- **Slug:** `order_status_processed`
- **Type:** Order Status
- **Active:** ✓ Yes
- **Subject:** `Tu pedido #{order_id} ha sido procesado`
- **Body:**
```
Hola {customer_name},

Tu pedido #{order_id} ha sido procesado exitosamente.

Estado actual: {order_status}
Total: ${order_total}
Fecha de entrega: {delivery_date}

Ver pedido: {tracking_url}
```

**3. Order Status - Pending** (`order_status_pending`)
- **Slug:** `order_status_pending`
- **Type:** Order Status
- **Active:** ✓ Yes
- **Subject:** `Tu pedido #{order_id} está pendiente`

**4. Order Status - Shipped** (`order_status_shipped`)
- **Slug:** `order_status_shipped`
- **Type:** Order Status
- **Active:** ✓ Yes
- **Subject:** `Tu pedido #{order_id} ha sido enviado`

**5. Order Status - Delivered** (`order_status_delivered`)
- **Slug:** `order_status_delivered`
- **Type:** Order Status
- **Active:** ✓ Yes
- **Subject:** `Tu pedido #{order_id} ha sido entregado`

**6. Order Status - Cancelled** (`order_status_cancelled`)
- **Slug:** `order_status_cancelled`
- **Type:** Order Status
- **Active:** ✓ Yes
- **Subject:** `Tu pedido #{order_id} ha sido cancelado`

## Testing After Fix

### 1. Create a Test Order

Place a test order in production (or staging) and watch the logs:

```bash
tail -f storage/logs/laravel.log
```

You should see:
```
Starting async order processing for order {id}
Sending order confirmation email
Email sent successfully: order_confirmation to customer@email.com
Sending order status email
Email sent successfully: order_status_processed to customer@email.com
```

### 2. Check Horizon Dashboard

Go to Horizon and verify:
- `ProcessOrderAsync` job completed successfully
- No failed jobs

### 3. Check Customer's Email

Verify the customer received both emails:
- Order confirmation email
- Order processed status email

## Common Issues & Solutions

### Issue: "Email template not found or inactive"

**Cause:** Template doesn't exist or `is_active = 0`

**Fix:** 
```bash
php artisan db:seed --class=EmailTemplatesSeeder
```

### Issue: Emails sent but not received

**Cause:** Email configuration issue (Mailgun/SMTP)

**Check:**
```bash
# Check mail settings in database
SELECT `key`, `value` FROM settings WHERE `key` LIKE 'mail%';
```

**Required settings:**
- `mail_mailer` → `mailgun` or `smtp`
- `mail_from_address` → Your from email
- `mail_from_name` → Your company name
- `mailgun_domain` → Your Mailgun domain
- `mailgun_secret` → Your Mailgun API key

**Test manually:**
```bash
php artisan tinker
>>> app(\App\Services\MailingService::class)->sendTemplateEmail('order_confirmation', ['customer_email' => 'test@test.com', 'order_id' => 123, 'customer_name' => 'Test', 'order_total' => '100', 'delivery_date' => 'TBD', 'order_url' => 'http://test.com']);
```

### Issue: Jobs processing but emails still not sending

**Check exception handling in ProcessOrderAsync:**

The job catches email exceptions and continues (lines 104-108 in ProcessOrderAsync.php):
```php
} catch (\Exception $e) {
    // Don't fail the job if email sending fails
    Log::error("Email sending failed for order {$this->order->id}: " . $e->getMessage());
}
```

**Look for these errors in logs:**
```bash
grep "Email sending failed" storage/logs/laravel.log
```

### Issue: Wrong template slug

The system looks for specific slug patterns:
- `order_confirmation` for new orders
- `order_status_{status}` for status changes (e.g., `order_status_processed`)

**Verify slugs match exactly:**
```sql
SELECT slug FROM email_templates WHERE slug LIKE 'order%';
```

## Verification Checklist

- [ ] Email templates exist in `email_templates` table
- [ ] All order-related templates have `is_active = 1`
- [ ] Template slugs match exactly: `order_confirmation`, `order_status_processed`, etc.
- [ ] Mail configuration is set in `settings` table or `.env`
- [ ] Mailgun/SMTP credentials are valid
- [ ] Horizon is running and processing jobs
- [ ] No errors in `storage/logs/laravel.log`
- [ ] Test order successfully sends emails

## Quick Fix Command

Run this in production to ensure everything is set up:

```bash
# 1. Seed email templates
php artisan db:seed --class=EmailTemplatesSeeder --force

# 2. Verify templates were created
php artisan tinker
>>> \App\Models\EmailTemplate::where('is_active', 1)->pluck('slug');
# Should show: order_confirmation, order_status_processed, etc.

# 3. Test email sending
>>> app(\App\Services\MailingService::class)->sendTemplateEmail('order_confirmation', ['customer_email' => 'your-email@test.com', 'order_id' => 999, 'customer_name' => 'Test User', 'order_total' => '100.00', 'delivery_date' => 'TBD', 'order_url' => route('home')]);
# Should return: true

# 4. Exit tinker
>>> exit
```

## Monitoring

Set up monitoring to catch future issues:

1. **Daily check of failed jobs:**
```bash
php artisan queue:failed
```

2. **Monitor email logs:**
```bash
grep "Email template not found" storage/logs/laravel.log
```

3. **Alert on email failures:**
Add to your monitoring:
- Alert if log contains "Email sending failed"
- Alert if no emails sent in last hour during business hours

## Support

If issues persist after following this guide:

1. Export full logs:
```bash
tail -n 500 storage/logs/laravel.log > email_debug.log
```

2. Check Horizon failed jobs:
```bash
php artisan horizon:failed
```

3. Verify database state:
```sql
SELECT COUNT(*) FROM email_templates WHERE is_active = 1;
SELECT * FROM settings WHERE `key` LIKE 'mail%';
```

Share these outputs for further debugging.
