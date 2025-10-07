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
            $content = str_replace($placeholder, (string) $value, $content);
            $subject = str_replace($placeholder, (string) $value, $subject);
        }

        return [
            'subject' => $subject,
            'body' => $content,
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
