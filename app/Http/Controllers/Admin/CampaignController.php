<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Promocion;
use App\Models\UpsellZone;
use App\Models\UpsellRule;
use App\Models\FeaturedProduct;
use App\Models\Banner;
use App\Models\VolumeDiscount;
use App\Models\Setting;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /**
     * Display the campaigns configuration dashboard
     */
    public function index()
    {
        // Get statistics for all campaign types
        $stats = [
            'coupons' => [
                'total' => Coupon::count(),
                'active' => Coupon::where('active', true)->count(),
                'expired' => Coupon::where('valid_to', '<', now())->count(),
                'recent' => Coupon::where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'promociones' => [
                'total' => Promocion::count(),
                'active' => Promocion::where('active', true)
                    ->where('valid_from', '<=', now())
                    ->where('valid_to', '>=', now())
                    ->count(),
                'expired' => Promocion::where('valid_to', '<', now())->count(),
                'upcoming' => Promocion::where('valid_from', '>', now())->count(),
            ],
            'upsell_zones' => [
                'total' => UpsellZone::count(),
                'active' => UpsellZone::where('active', true)->count(),
                'with_rules' => UpsellZone::whereHas('rules')->count(),
            ],
            'upsell_rules' => [
                'total' => UpsellRule::count(),
                'active' => UpsellRule::where('active', true)->count(),
            ],
            'featured_products' => [
                'total' => FeaturedProduct::count(),
            ],
            'banners' => [
                'total' => Banner::count(),
                'active' => Banner::count(), // Banner model doesn't have active field
            ],
            'volume_discounts' => [
                'total' => VolumeDiscount::count(),
                'active' => VolumeDiscount::where('active', true)->count(),
            ],
        ];

        // Get recent activity
        $recentCoupons = Coupon::latest()->take(5)->get();
        $recentPromociones = Promocion::latest()->take(5)->get();

        // Get campaign-related settings
        $settings = Setting::whereIn('key', [
            'auto_tag_nuevo_enabled',
            'auto_tag_descuento_enabled',
            'use_most_sold_products',
            'featured_products_section_title',
        ])->get()->keyBy('key');

        $context = compact('stats', 'recentCoupons', 'recentPromociones', 'settings');

        return view('admin.campaigns.index', $context);
    }

    /**
     * Update campaign settings
     */
    public function updateSettings(Request $request)
    {
        // Handle boolean toggles explicitly (hidden input sends "0" when unchecked)
        $booleanFields = [
            'auto_tag_nuevo_enabled',
            'auto_tag_descuento_enabled',
            'use_most_sold_products',
        ];

        foreach ($booleanFields as $field) {
            $value = $request->input($field, '0');
            Setting::updateOrCreate(
                ['key' => $field],
                [
                    'name' => $this->getSettingName($field),
                    'value' => $value ? '1' : '0',
                    'show' => false,
                ]
            );
        }

        // Handle text fields
        if ($request->has('featured_products_section_title')) {
            $title = $request->input('featured_products_section_title', 'Productos Destacados');
            Setting::updateOrCreate(
                ['key' => 'featured_products_section_title'],
                [
                    'name' => $this->getSettingName('featured_products_section_title'),
                    'value' => $title,
                    'show' => false,
                ]
            );
        }

        return back()->with('success', 'Configuración de campañas actualizada exitosamente');
    }

    /**
     * Get human-readable setting name
     */
    private function getSettingName($key)
    {
        $names = [
            'auto_tag_nuevo_enabled' => 'Etiqueta Automática NUEVO',
            'auto_tag_descuento_enabled' => 'Etiqueta Automática DESCUENTO',
            'use_most_sold_products' => 'Usar Productos Más Vendidos',
            'featured_products_section_title' => 'Título de Productos Destacados',
        ];

        return $names[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
}
