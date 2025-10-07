# Email Templates with Rich Text Editor and Images

This document explains how to use the enhanced email template system with rich text editing and header/footer images.

## Features

- **Rich Text Editor**: Powered by Quill.js for formatting email content
- **Header Image**: Upload a custom image to appear at the top of emails
- **Footer Image**: Upload a custom image to appear at the bottom of emails
- **Variable Replacement**: Use placeholders like `{customer_name}`, `{order_id}`, etc.
- **Image Management**: Automatically deletes old images when uploading new ones

## Creating/Editing Email Templates

### 1. Access Email Templates

Navigate to **Admin Panel** → **Email Templates**

### 2. Create or Edit a Template

#### Basic Information
- **Name**: Descriptive name for the template
- **Slug**: Unique identifier (e.g., `order_confirmation`)
- **Type**: Select the template type (Order Status, Order Confirmation, etc.)
- **Subject**: Email subject line (can use variables)
- **Active**: Toggle to enable/disable the template

#### Content
- Use the **rich text editor** to format your email content
- Available formatting options:
  - Headers (H2, H3, H4)
  - **Bold**, *Italic*, Underline
  - Ordered and unordered lists
  - Links
  - Text alignment

#### Images
- **Header Image**: Upload an image to appear at the top of the email (max 2MB)
- **Footer Image**: Upload an image to appear at the bottom of the email (max 2MB)
- Supported formats: JPG, PNG, GIF, SVG
- Images are stored in `storage/app/public/email-templates/`

### 3. Using Variables

Click on available variables to insert them into your content. Variables are replaced with actual data when the email is sent.

**Example:**
```
Hola {customer_name},

Tu pedido #{order_id} ha sido confirmado.

Total: ${order_total}
```

## Using Templates Programmatically

### Method 1: Using getEmailData() (Recommended)

```php
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Mail;

// Get the template
$template = EmailTemplate::where('slug', 'order_confirmation')
    ->where('is_active', true)
    ->first();

// Prepare variable data
$variableData = [
    'customer_name' => $order->user->name,
    'order_id' => $order->id,
    'order_total' => number_format($order->total, 2),
    'order_date' => $order->created_at->format('d/m/Y'),
];

// Get all email data including images
$emailData = $template->getEmailData($variableData);

// Send the email
Mail::send('emails.order', $emailData, function ($message) use ($order, $emailData) {
    $message->to($order->user->email)
            ->subject($emailData['subject']);
});
```

### Method 2: Manual Approach

```php
use App\Models\EmailTemplate;

$template = EmailTemplate::where('slug', 'order_confirmation')->first();

// Replace variables
$processed = $template->replaceVariables([
    'customer_name' => 'Juan Pérez',
    'order_id' => '12345',
]);

// Get image URLs
$headerImage = $template->getHeaderImageUrl();
$footerImage = $template->getFooterImageUrl();

// Pass to email view
$emailData = [
    'body' => $processed['body'],
    'headerImage' => $headerImage,
    'footerImage' => $footerImage,
];
```

## Email Layout Integration

The email layout (`resources/views/layouts/email.blade.php`) automatically includes header and footer images when provided:

```blade
@extends('layouts.email')

@section('content')
    {!! $body !!}
@endsection
```

The layout expects these variables:
- `$headerImage` - Full URL to header image (optional)
- `$footerImage` - Full URL to footer image (optional)
- `$body` - HTML content of the email

## Available Template Types

1. **Order Status** - For order status updates
   - Variables: `order_id`, `order_status`, `customer_name`, `customer_email`, `order_total`, `order_date`, `delivery_date`, `tracking_url`

2. **Order Confirmation** - For order confirmations
   - Variables: `order_id`, `customer_name`, `customer_email`, `order_total`, `order_products`, `delivery_date`, `order_url`

3. **User Registration** - For new user registrations
   - Variables: `customer_name`, `customer_email`, `activation_link`, `login_url`

4. **Contact Form** - For contact form submissions (to admin)
   - Variables: `contact_name`, `contact_email`, `contact_phone`, `business_name`, `city`, `nit`, `message`, `contact_date`

## Image Best Practices

### Recommended Dimensions
- **Header Image**: 640px width (height proportional)
- **Footer Image**: 640px width (height proportional)

### File Size
- Maximum: 2MB per image
- Recommended: Under 500KB for faster email loading

### Image Types
- Use **PNG** for logos and images with transparency
- Use **JPG** for photos
- Optimize images before uploading for best performance

### Email Client Compatibility
- Test emails across different clients (Gmail, Outlook, Apple Mail)
- Use high-contrast images for better visibility
- Ensure images have meaningful alt text

## Troubleshooting

### Images not showing in emails?

1. **Check storage link**: Ensure `php artisan storage:link` has been run
2. **Verify file exists**: Check that the image file exists in `storage/app/public/email-templates/`
3. **Check permissions**: Ensure the storage directory is writable
4. **Absolute URLs**: Use `asset('storage/...')` for full URLs in emails

### Rich text editor not loading?

1. **Build assets**: Run `npm run build` or `npm run dev`
2. **Check Vue**: Ensure Vue 3 and Quill.js are properly installed
3. **Console errors**: Check browser console for JavaScript errors

### Variables not being replaced?

1. **Check spelling**: Ensure variable names match exactly (case-sensitive)
2. **Curly braces**: Variables must be wrapped in `{variable_name}`
3. **Pass all data**: Ensure all variables are included in the `$variableData` array

## Migration

To run the migration that adds image fields:

```bash
php artisan migrate
```

This adds the following columns to `email_templates` table:
- `header_image` (nullable string)
- `footer_image` (nullable string)

## Example: Complete Email Flow

```php
use App\Models\EmailTemplate;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;

class OrderService
{
    public function sendOrderConfirmation(Order $order)
    {
        // Get active template
        $template = EmailTemplate::active()
            ->byType(EmailTemplate::TYPE_ORDER_CONFIRMATION)
            ->first();
            
        if (!$template) {
            \Log::warning('Order confirmation template not found');
            return;
        }
        
        // Prepare variables
        $variables = [
            'customer_name' => $order->user->name,
            'customer_email' => $order->user->email,
            'order_id' => $order->id,
            'order_total' => '$' . number_format($order->total, 2),
            'order_date' => $order->created_at->format('d/m/Y'),
            'delivery_date' => $order->delivery_date->format('d/m/Y'),
            'order_url' => route('orders.show', $order),
        ];
        
        // Get email data with images
        $emailData = $template->getEmailData($variables);
        
        // Send email
        Mail::send('emails.template', $emailData, function ($message) use ($order, $emailData) {
            $message->to($order->user->email)
                    ->subject($emailData['subject']);
        });
    }
}
```

## Additional Resources

- [Quill.js Documentation](https://quilljs.com/docs/quickstart/)
- [Laravel Mail Documentation](https://laravel.com/docs/mail)
- [Email Design Best Practices](https://www.campaignmonitor.com/resources/)

