# Troubleshooting Email Issues

## Error: "Unable to send an email: Forbidden (Code 401)"

This error occurs when Mailgun cannot authenticate your API credentials. Here's how to fix it:

### Solution 1: Verify Mailgun Credentials (Recommended)

1. **Get your Mailgun API Key:**

    - Go to https://app.mailgun.com/settings/api_security
    - Copy your **Private API Key** (starts with `key-`)
    - IMPORTANT: Use the **Private API Key**, NOT the Public API Key

2. **Get your Mailgun Domain:**

    - Go to https://app.mailgun.com/domains
    - Copy your verified sending domain (e.g., `mg.tuti.com` or `tuti.com`)
    - Make sure the domain shows as "Verified" with a green checkmark

3. **Update Settings in Tuti:**

    - Navigate to: Settings → Mailer Configuration
    - Select **Mailgun API** as the Mail Driver
    - Enter your Mailgun Domain
    - Enter your Mailgun Secret (API Key)
    - Select the correct endpoint:
        - `api.mailgun.net` for US accounts
        - `api.eu.mailgun.net` for EU accounts
    - Click **Save Configuration**

4. **Test Again:**
    - Enter a test email address
    - Click **Enviar Prueba**

### Solution 2: Switch to SMTP (Alternative)

If you don't have Mailgun or prefer SMTP:

1. **Navigate to:** Settings → Mailer Configuration
2. **Select** SMTP as the Mail Driver
3. **Enter SMTP details:**
    - Server: Your SMTP server (e.g., `smtp.gmail.com`, `smtp.office365.com`)
    - Port: Usually 587 (TLS) or 465 (SSL)
    - Username: Your email address
    - Password: Your email password or app-specific password
    - Encryption: TLS (recommended)
4. **Save and test**

#### Common SMTP Providers:

**Gmail:**

-   Host: `smtp.gmail.com`
-   Port: 587
-   Encryption: TLS
-   Username: your-email@gmail.com
-   Password: App-specific password (not your regular password)
-   Enable "Less secure app access" or use App Password

**Office 365:**

-   Host: `smtp.office365.com`
-   Port: 587
-   Encryption: TLS
-   Username: your-email@company.com
-   Password: Your account password

**Mailgun via SMTP (not recommended, use API instead):**

-   Host: `smtp.mailgun.org`
-   Port: 587
-   Username: postmaster@your-domain.com
-   Password: Your Mailgun SMTP password

### Solution 3: Use Log Driver for Testing

For development/testing only:

1. Select **Log** as the Mail Driver
2. Emails won't be sent but will be written to `storage/logs/laravel.log`
3. This helps verify your email templates work without actually sending

## Common Issues

### "Mailgun selected but credentials missing"

-   Make sure both Domain AND Secret are filled in
-   Check that the values don't have extra spaces
-   Verify you're using the correct API key format

### "Connection timeout" (SMTP)

-   Your hosting provider might block SMTP ports
-   Try alternative port (2525 or 465)
-   Consider using Mailgun API instead of SMTP

### "Could not instantiate mail function"

-   Your server doesn't have mail functions enabled
-   Use SMTP or Mailgun instead of sendmail

## Checking Your Settings

You can verify your settings are saved correctly by checking the database:

```bash
php artisan tinker
>>> \App\Models\Setting::whereIn('key', ['mail_mailer', 'mailgun_domain', 'mailgun_secret'])->get(['key', 'value']);
```

## Need More Help?

Check the Laravel logs for detailed error messages:

```bash
tail -f storage/logs/laravel.log
```

## Testing from Command Line

You can test email from the command line:

```bash
php artisan tinker
>>> Mail::raw('Test email', function($msg) { $msg->to('test@example.com')->subject('Test'); });
```

Any errors will be displayed immediately.

