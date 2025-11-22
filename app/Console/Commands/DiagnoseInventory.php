<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\User;
use App\Models\ZoneWarehouse;
use Illuminate\Console\Command;

class DiagnoseInventory extends Command
{
    protected $signature = 'inventory:diagnose {user_email?}';
    protected $description = 'Diagnose inventory availability issues for a user';

    public function handle()
    {
        $userEmail = $this->argument('user_email');
        
        if (!$userEmail) {
            $userEmail = $this->ask('Enter user email to diagnose');
        }

        $user = User::where('email', $userEmail)->first();

        if (!$user) {
            $this->error("User not found: {$userEmail}");
            return 1;
        }

        $this->info("=== INVENTORY DIAGNOSTIC FOR {$user->name} ({$user->email}) ===\n");

        // 1. Check user zones
        $this->info("1. USER ZONES:");
        $this->line("   user->zone (string): " . ($user->zone ?? 'NULL'));
        
        $zones = $user->zones;
        if ($zones->count() > 0) {
            foreach ($zones as $zone) {
                $this->line("   Zone ID {$zone->id}:");
                $this->line("     - code: " . ($zone->code ?? 'NULL'));
                $this->line("     - zone: " . ($zone->zone ?? 'NULL'));
                $this->line("     - address: " . ($zone->address ?? 'NULL'));
            }
        } else {
            $this->warn("   No zones found in zones relationship");
        }
        $this->line("");

        // 2. Determine zone code (using same logic as PageController)
        $this->info("2. ZONE CODE DETERMINATION:");
        $zoneCode = $user->zone ?? null;
        $this->line("   Step 1 - user->zone: " . ($zoneCode ?? 'NULL'));

        if (!$zoneCode) {
            $zoneCode = $user->zones()->orderBy('id')->value('code');
            $this->line("   Step 2 - zones()->code: " . ($zoneCode ?? 'NULL'));
        }
        
        if (!$zoneCode) {
            $zoneCode = $user->zones()->orderBy('id')->value('zone');
            $this->line("   Step 3 - zones()->zone: " . ($zoneCode ?? 'NULL'));
        }

        if (!$zoneCode) {
            $this->error("   PROBLEM: No zone code found for this user!");
            $this->line("   SOLUTION: Assign a zone to this user with a valid 'code' field");
            $this->line("");
        } else {
            $this->info("   ✓ Final zone code: {$zoneCode}");
            $this->line("");
        }

        // 3. Check zone-warehouse mapping
        $this->info("3. ZONE-WAREHOUSE MAPPING:");
        if ($zoneCode) {
            $bodega = ZoneWarehouse::where('zone_code', $zoneCode)->value('bodega_code');
            if (!$bodega) {
                $bodega = ZoneWarehouse::whereRaw('LOWER(zone_code) = ?', [mb_strtolower($zoneCode)])->value('bodega_code');
            }

            if ($bodega) {
                $this->info("   ✓ Bodega code: {$bodega}");
            } else {
                $this->error("   PROBLEM: No warehouse mapping found for zone code '{$zoneCode}'");
                $this->line("   Available zone mappings:");
                ZoneWarehouse::all()->each(function($zw) {
                    $this->line("     - zone: {$zw->zone_code} → bodega: {$zw->bodega_code}");
                });
                $this->line("   SOLUTION: Create a zone_warehouses record:");
                $this->line("   INSERT INTO zone_warehouses (zone_code, bodega_code) VALUES ('{$zoneCode}', 'DESIRED_BODEGA');");
            }
        } else {
            $this->warn("   Skipped (no zone code)");
        }
        $this->line("");

        // 4. Check inventory settings
        $this->info("4. INVENTORY SYSTEM:");
        $inventoryEnabled = \App\Models\Setting::getByKey('inventory_enabled');
        $isEnabled = ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true);
        $this->line("   inventory_enabled setting: " . ($isEnabled ? 'ENABLED ✓' : 'DISABLED'));
        $this->line("");

        // 5. Check sample products
        $this->info("5. SAMPLE PRODUCTS:");
        $products = Product::active()->with('inventories')->limit(3)->get();
        
        foreach ($products as $product) {
            $this->line("   Product: {$product->name} (ID: {$product->id})");
            $this->line("     - inventory_opt_out: " . ($product->inventory_opt_out ? 'YES (excluded from management)' : 'NO'));
            $this->line("     - variation_id: " . ($product->variation_id ?? 'NULL (no variations)'));
            $this->line("     - isInventoryManaged(): " . ($product->isInventoryManaged() ? 'YES' : 'NO'));
            
            if ($product->inventories->count() > 0) {
                $this->line("     - Inventory records:");
                foreach ($product->inventories as $inv) {
                    $this->line("       * Bodega: {$inv->bodega_code}, Available: {$inv->available}, Reserved: {$inv->reserved}");
                }
            } else {
                $this->warn("       NO INVENTORY RECORDS");
            }

            if (isset($bodega)) {
                $available = $product->getInventoryForBodega($bodega);
                $this->line("     - Available for user's bodega ({$bodega}): " . $available);
                
                if ($available <= 0) {
                    $this->error("       ⚠ This product will show as UNAVAILABLE for this user");
                } else {
                    $this->info("       ✓ This product is AVAILABLE for this user");
                }
            }
            $this->line("");
        }

        $this->info("=== DIAGNOSIS COMPLETE ===\n");

        // Summary and recommendations
        $this->info("SUMMARY:");
        if (!$zoneCode) {
            $this->error("❌ User has no zone code → Products show as unavailable");
            $this->line("   Fix: Assign a zone with a valid 'code' field to this user");
        } elseif (!isset($bodega)) {
            $this->error("❌ Zone code '{$zoneCode}' has no warehouse mapping → Products show as unavailable");
            $this->line("   Fix: Create a zone_warehouses record for this zone code");
        } else {
            $this->info("✓ User zone and warehouse mapping are correct");
            $this->line("  If products still show unavailable, check:");
            $this->line("  - Product inventory records exist for bodega '{$bodega}'");
            $this->line("  - Available qty > 0 for that bodega");
            $this->line("  - Product is not excluded via inventory_opt_out");
        }

        return 0;
    }
}

