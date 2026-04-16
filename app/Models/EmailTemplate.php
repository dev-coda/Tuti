<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'subject',
        'body',
        'header_image',
        'footer_image',
        'variables',
        'is_active',
        'type'
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    const TYPE_ORDER_STATUS = 'order_status';
    const TYPE_USER_REGISTRATION = 'user_registration';
    const TYPE_CONTACT_FORM = 'contact_form';
    const TYPE_ORDER_CONFIRMATION = 'order_confirmation';

    /**
     * Get the available template types
     */
    public static function getTypes()
    {
        return [
            self::TYPE_ORDER_STATUS => 'Order Status Change',
            self::TYPE_USER_REGISTRATION => 'User Registration',
            self::TYPE_CONTACT_FORM => 'Contact Form (Admin)',
            self::TYPE_ORDER_CONFIRMATION => 'Order Confirmation',
        ];
    }

    /**
     * Get default variables for each template type
     */
    public function getDefaultVariables()
    {
        $defaults = [
            self::TYPE_ORDER_STATUS => [
                'order_id',
                'order_status',
                'customer_name',
                'customer_email',
                'order_total',
                'order_date',
                'delivery_date',
                'tracking_url',
            ],
            self::TYPE_USER_REGISTRATION => [
                'customer_name',
                'customer_email',
                'activation_link',
                'login_url',
            ],
            self::TYPE_CONTACT_FORM => [
                'contact_name',
                'contact_email',
                'contact_phone',
                'business_name',
                'city',
                'nit',
                'message',
                'contact_date',
            ],
            self::TYPE_ORDER_CONFIRMATION => [
                'order_id',
                'customer_name',
                'customer_email',
                'order_total',
                'order_products',
                'delivery_date',
                'order_url',
            ],
        ];

        return $defaults[$this->type] ?? [];
    }

    /**
     * Replace variables in the template
     */
    public function replaceVariables($data = [])
    {
        $content = $this->body;
        $subject = $this->subject;

        foreach ($data as $key => $value) {
            $placeholder = '{' . $key . '}';
            if ($key === 'order_products' && is_array($value)) {
                $replaced = self::formatOrderProductsHtmlRows($value);
                $content = str_replace($placeholder, $replaced, $content);
                // Never inject HTML rows into subject lines
                $subject = str_replace($placeholder, '', $subject);
                continue;
            }
            $replaced = (string) $value;
            $content = str_replace($placeholder, $replaced, $content);
            $subject = str_replace($placeholder, $replaced, $subject);
        }

        return [
            'subject' => trim($subject),
            'body' => $content,
        ];
    }

    /**
     * Build HTML table rows for order line items (order confirmation emails).
     *
     * @param  array<int, array{name?: string, quantity?: int|float|string, price?: string|float|int}>  $products
     */
    public static function formatOrderProductsHtmlRows(array $products): string
    {
        $rows = '';
        $border = 'border-bottom: 1px solid #e7e6e4;';
        foreach ($products as $line) {
            $name = htmlspecialchars((string) ($line['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $qty = htmlspecialchars((string) ($line['quantity'] ?? ''), ENT_QUOTES, 'UTF-8');
            $priceRaw = trim((string) ($line['price'] ?? ''));
            $priceRaw = preg_replace('/^\s*\$\s*/u', '', $priceRaw);
            $price = htmlspecialchars($priceRaw, ENT_QUOTES, 'UTF-8');
            $rows .= '<tr>'
                . '<td style="padding: 12px; ' . $border . '">' . $name . '</td>'
                . '<td style="padding: 12px; text-align: center; ' . $border . '">' . $qty . '</td>'
                . '<td style="padding: 12px; text-align: right; ' . $border . '">$' . $price . '</td>'
                . '</tr>';
        }

        return $rows;
    }

    /**
     * Get the full URL for the header image
     */
    public function getHeaderImageUrl()
    {
        if (!$this->header_image) {
            return null;
        }
        
        return asset('storage/' . $this->header_image);
    }

    /**
     * Header image for HTML emails: custom template image or default Tuti logo (single banner, no duplicate in-body logo).
     */
    public function getHeaderImageUrlForLayout(): string
    {
        return $this->getHeaderImageUrl() ?? asset('img/tuti.png');
    }

    /**
     * Get the full URL for the footer image
     */
    public function getFooterImageUrl()
    {
        if (!$this->footer_image) {
            return null;
        }
        
        return asset('storage/' . $this->footer_image);
    }

    /**
     * Get template data for email rendering including images
     */
    public function getEmailData($variablesData = [])
    {
        $processed = $this->replaceVariables($variablesData);
        
        return [
            'subject' => $processed['subject'],
            'body' => $processed['body'],
            'headerImage' => $this->getHeaderImageUrlForLayout(),
            'footerImage' => $this->getFooterImageUrl(),
        ];
    }

    /**
     * Scope to get active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get templates by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
