# Internal Email Filter - Block Tuti Domain Emails

**Date:** November 21, 2025  
**Status:** ✅ Implemented  
**Type:** Email Safety Feature

## Purpose

Prevents emails from being sent to internal Tuti email addresses while allowing the workflow to continue uninterrupted. This ensures that:
- Test accounts with Tuti emails don't receive production emails
- Internal staff accounts don't trigger email sends
- Workflow and order processing continue normally
- No errors or exceptions are thrown

## Blocked Domains

The following email domains are blocked:
- `@tuti`
- `@tuti.com`
- `@tuti.com.co`

## Implementation

### Location
`app/Services/MailingService.php`

### Method: `isInternalTutiEmail()`
```php
private function isInternalTutiEmail(string $email): bool
{
    $email = strtolower(trim($email));
    
    $internalDomains = [
        '@tuti',
        '@tuti.com',
        '@tuti.com.co'
    ];

    foreach ($internalDomains as $domain) {
        if (str_ends_with($email, $domain)) {
            return true;
        }
    }

    return false;
}
```

### Integration in `sendTemplateEmail()`
```php
// Filter: Block emails to internal Tuti domains
if ($this->isInternalTutiEmail($to)) {
    Log::info("Email blocked (internal Tuti domain): {$templateSlug} to {$to}");
    return true; // Return true to allow workflow to continue
}
```

## Behavior

### When Email is Blocked

**Action:** 
- Email is NOT sent
- Method returns `true` (success)
- Logged as: `"Email blocked (internal Tuti domain): {template} to {email}"`

**Result:**
- ✅ Order processing continues
- ✅ Job completes successfully
- ✅ No exceptions thrown
- ✅ Workflow unaffected

### When Email is Allowed

**Action:**
- Email is sent normally
- Logged as: `"Email sent successfully: {template} to {email}"`

**Result:**
- ✅ Customer receives email
- ✅ Normal workflow

## Examples

### Blocked Emails
```
admin@tuti ...................... ✓ BLOCKED
test@tuti.com ................... ✓ BLOCKED
developer@tuti.com.co ........... ✓ BLOCKED
ADMIN@TUTI.COM .................. ✓ BLOCKED (case-insensitive)
```

### Allowed Emails
```
customer@gmail.com .............. ✓ SENT
shop@example.com ................ ✓ SENT
user@tutipet.com ................ ✓ SENT (different domain)
admin@mytuti.com ................ ✓ SENT (not exact match)
```

## Use Cases

### 1. Testing in Production
```
User: admin@tuti.com
Order: #12345
Result: Order processed ✓, Email blocked ✓, No customer email sent ✓
```

### 2. Internal Staff Orders
```
User: staff@tuti.com.co
Order: #12346
Result: Order processed ✓, Email blocked ✓, Workflow continues ✓
```

### 3. Real Customer Orders
```
User: customer@gmail.com
Order: #12347
Result: Order processed ✓, Email sent ✓, Customer receives confirmation ✓
```

## Logging

All blocked emails are logged for monitoring:

```bash
# View blocked emails
tail -f storage/logs/laravel.log | grep "Email blocked (internal Tuti domain)"
```

Example log entries:
```
[2025-11-21 10:30:45] local.INFO: Email blocked (internal Tuti domain): order_confirmation to admin@tuti.com
[2025-11-21 10:31:12] local.INFO: Email blocked (internal Tuti domain): order_status_processed to test@tuti.com.co
```

## Configuration

### Adding More Blocked Domains

Edit `app/Services/MailingService.php`:

```php
$internalDomains = [
    '@tuti',
    '@tuti.com',
    '@tuti.com.co',
    '@newtutidomain.com'  // Add new domain here
];
```

### Removing Domains

Simply remove the domain from the `$internalDomains` array.

### Disable Filter Temporarily

Comment out the filter check in `sendTemplateEmail()`:

```php
// TEMPORARY: Allow internal emails
// if ($this->isInternalTutiEmail($to)) {
//     Log::info("Email blocked (internal Tuti domain): {$templateSlug} to {$to}");
//     return true;
// }
```

## Testing

### Test 1: Block Internal Email
```bash
php artisan tinker
>>> $service = app(\App\Services\MailingService::class);
>>> $result = $service->sendTemplateEmail('order_confirmation', [
    'customer_email' => 'admin@tuti.com',
    'order_id' => 999,
    'customer_name' => 'Admin',
    'order_total' => '100.00',
    'delivery_date' => 'TBD',
    'order_url' => route('home')
]);
# Should return: true
# Check log: grep "Email blocked" storage/logs/laravel.log
```

### Test 2: Allow External Email
```bash
php artisan tinker
>>> $service = app(\App\Services\MailingService::class);
>>> $result = $service->sendTemplateEmail('order_confirmation', [
    'customer_email' => 'test@example.com',
    'order_id' => 999,
    'customer_name' => 'Test User',
    'order_total' => '100.00',
    'delivery_date' => 'TBD',
    'order_url' => route('home')
]);
# Should return: true
# Email should actually be sent
```

### Test 3: Create Order with Internal Email
```
1. Create a test user with email: test@tuti.com
2. Place an order as that user
3. Check logs - order should process
4. Check logs - email should be blocked
5. Verify no email was sent to test@tuti.com
```

## Monitoring

### Daily Check
```bash
# Count blocked emails today
grep "$(date +%Y-%m-%d)" storage/logs/laravel.log | grep -c "Email blocked (internal Tuti domain)"
```

### By Domain
```bash
# Check blocked emails by domain
grep "Email blocked (internal Tuti domain)" storage/logs/laravel.log | grep -o "@[^[:space:]]*" | sort | uniq -c
```

### Recent Blocks
```bash
# Last 10 blocked emails
grep "Email blocked (internal Tuti domain)" storage/logs/laravel.log | tail -10
```

## Impact

### Positive
- ✅ Prevents spam to internal accounts
- ✅ Safe testing in production
- ✅ No workflow interruption
- ✅ Clean logs showing what was blocked

### Neutral
- ℹ️ Internal users won't receive order emails
- ℹ️ Must use external email for testing actual email delivery

### Negative
- None - this is intentional behavior

## Security Considerations

### Why Return True?
Returning `true` (success) when blocking emails is intentional:
- Prevents workflow failures
- Maintains order processing
- Avoids retry loops
- Keeps job queues clean

### Alternative: Return False?
**Not recommended** because:
- ❌ Would mark order processing as failed
- ❌ Would trigger job retries
- ❌ Would require exception handling everywhere
- ❌ Would complicate workflows

## Future Enhancements

### Make Configurable via Database
```php
// Store blocked domains in settings table
$blockedDomains = Setting::getByKey('email_blocked_domains');
// Format: "@tuti,@tuti.com,@tuti.com.co"
```

### Environment-Specific Behavior
```php
// Only block in production
if (app()->environment('production') && $this->isInternalTutiEmail($to)) {
    // Block email
}
```

### Admin Override
```php
// Allow admins to receive emails via setting
$allowAdminEmails = Setting::getByKey('allow_admin_emails');
if (!$allowAdminEmails && $this->isInternalTutiEmail($to)) {
    // Block email
}
```

## Troubleshooting

### Issue: Legitimate emails being blocked

**Check:** Is the domain in the blocked list?
```php
// In MailingService.php
$internalDomains = [
    '@tuti',
    '@tuti.com', 
    '@tuti.com.co'
];
```

**Solution:** Remove the domain or use a different email address.

### Issue: Need to test with Tuti email

**Solution:** 
1. Temporarily comment out the filter
2. Test
3. Uncomment the filter
4. OR use a test email service like mailtrap.io

### Issue: Can't find blocked email logs

**Search:**
```bash
grep "Email blocked" storage/logs/laravel.log
```

If nothing found, the filter might not be active or no internal emails were attempted.

## Related Files

- `app/Services/MailingService.php` - Filter implementation
- `app/Jobs/ProcessOrderAsync.php` - Uses MailingService
- `docs/EMAIL_TROUBLESHOOTING.md` - Email troubleshooting guide

---

**Implemented:** November 21, 2025  
**Status:** ✅ Active  
**Breaking Changes:** None  
**Migration Required:** No


