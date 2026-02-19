<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UpsellZone;
use App\Models\UpsellRule;

class UpsellZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default rules
        $rules = [
            [
                'name' => 'Productos de Pedidos Recientes',
                'type' => 'recent_orders',
                'description' => 'Muestra productos que el cliente ha comprado recientemente',
                'config' => ['days' => 90, 'limit' => 10],
                'priority' => 100,
                'active' => true,
            ],
            [
                'name' => 'Productos Favoritos',
                'type' => 'favorite_products',
                'description' => 'Muestra productos marcados como favoritos por el cliente',
                'config' => ['limit' => 10],
                'priority' => 90,
                'active' => true,
            ],
            [
                'name' => 'Misma Categoría',
                'type' => 'same_category',
                'description' => 'Muestra productos de la misma categoría',
                'config' => ['limit' => 10],
                'priority' => 80,
                'active' => true,
            ],
            [
                'name' => 'Misma Marca',
                'type' => 'same_brand',
                'description' => 'Muestra productos de la misma marca',
                'config' => ['limit' => 10],
                'priority' => 70,
                'active' => true,
            ],
            [
                'name' => 'Más Vendidos',
                'type' => 'best_selling',
                'description' => 'Muestra los productos más vendidos',
                'config' => ['limit' => 10],
                'priority' => 60,
                'active' => true,
            ],
            [
                'name' => 'Productos Relacionados',
                'type' => 'related_products',
                'description' => 'Muestra productos relacionados desde la tabla product_related',
                'config' => ['limit' => 10],
                'priority' => 50,
                'active' => true,
            ],
        ];

        $createdRules = [];
        foreach ($rules as $ruleData) {
            $rule = UpsellRule::firstOrCreate(
                ['type' => $ruleData['type']],
                $ruleData
            );
            $createdRules[] = $rule;
        }

        // Create default zone "Complementa tu compra"
        $zone = UpsellZone::firstOrCreate(
            ['slug' => 'complementa-tu-compra'],
            [
                'name' => 'Complementa tu compra',
                'description' => 'Zona de productos complementarios en la página de detalle de producto',
                'display_title' => 'Complementa tu compra',
                'active' => true,
                'position' => 0,
                'max_products' => 4,
                'context' => 'product_detail',
            ]
        );

        // Attach rules to zone with priorities
        if ($zone->wasRecentlyCreated) {
            $zoneRules = [];
            foreach ($createdRules as $index => $rule) {
                $zoneRules[$rule->id] = [
                    'priority' => $rule->priority,
                    'active' => true,
                ];
            }
            $zone->rules()->attach($zoneRules);
        }
    }
}
