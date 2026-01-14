# Global Inventory Minimum System

## Overview

The Global Inventory Minimum system provides a configurable safety net for inventory management across all products, while respecting product-specific safety stock settings.

## How It Works

### Logic Hierarchy

1. **Product Safety Stock Has Precedence**: If a product has a safety stock configured (> 0), that value is used
2. **Global Minimum as Fallback**: If a product has NO safety stock configured (= 0), the global minimum applies
3. **Smart Threshold**: The system uses the appropriate threshold to prevent overselling

### Examples

| Product Safety Stock | Global Minimum | Effective Minimum Used | Reasoning |
|---------------------|----------------|------------------------|-----------|
| 10 units | 5 units | **10 units** | Product safety stock takes precedence |
| 3 units | 5 units | **3 units** | Product safety stock takes precedence (even if lower) |
| 0 units | 5 units | **5 units** | Global minimum applies as fallback |
| Not configured | 5 units | **5 units** | Global minimum applies as fallback |

## Configuration

### Admin Panel

1. Navigate to **Settings** (Configuración)
2. Find the **"Inventario Global"** section at the top
3. Set the **"Inventario Mínimo Global"** value (default: 5 units)
4. Click **"Guardar"** to save

### Valid Range

- **Minimum**: 0 units
- **Maximum**: 100 units
- **Default**: 5 units

### Important Notes

⚠️ **The global minimum only applies to products WITHOUT their own safety stock configured**

✅ **Product-level safety stock always takes precedence when configured**

## Technical Implementation

### Database

The setting is stored in the `settings` table:

```php
Setting::updateOrCreate(
    ['key' => 'global_minimum_inventory'],
    [
        'name' => 'Inventario Mínimo Global',
        'value' => 5, // configurable value
        'show' => false,
    ]
);
```

### Inventory Checks

The system performs two validation checks:

#### 1. Initial Cart Validation (`CartController.php` ~line 890)

Prevents adding products to cart if inventory is insufficient:

```php
$safety = (int) $product->getEffectiveSafetyStock();
$globalMinInventory = (int) (Setting::getByKey('global_minimum_inventory') ?? 5);

// Use product safety stock if configured (> 0), otherwise use global minimum
$effectiveMinimum = ($safety > 0) ? $safety : $globalMinInventory;

if ($available <= $effectiveMinimum) {
    // Block order
}
```

#### 2. Final Order Processing (`CartController.php` ~line 1250)

Performs a locked transaction check before decrementing inventory:

```php
$inventory = ProductInventory::lockForUpdate()->where('product_id', $p->id)->where('bodega_code', $bodega)->first();
$current = (int) ($inventory?->available ?? 0);
$safety = (int) $p->getEffectiveSafetyStock();
$globalMinInventory = (int) (Setting::getByKey('global_minimum_inventory') ?? 5);
$effectiveMinimum = ($safety > 0) ? $safety : $globalMinInventory;

if ($current <= $effectiveMinimum || ($current - (int)$row['quantity']) < $effectiveMinimum || $row['quantity'] > $current) {
    DB::rollBack();
    // Block order
}
```

### Logging

All blocked orders are logged with detailed information:

```php
\Log::warning('Order blocked: below minimum threshold', [
    'product_id' => $product->id,
    'product_name' => $product->name,
    'available' => $available,
    'product_safety_stock' => $safety,
    'global_minimum' => $globalMinInventory,
    'effective_minimum' => $effectiveMinimum,
    'reason' => ($safety > 0) ? 'product safety stock' : 'global minimum inventory',
    'bodega' => $bodega
]);
```

## User Experience

### Error Messages

- **Product with safety stock**: `"{Product Name} está por debajo del stock de seguridad."`
- **Product using global minimum**: `"El producto {Product Name} tiene inventario insuficiente en su zona (mínimo: {X} unidades)."`

### Visual Feedback

The global minimum setting in the admin panel includes:
- Clear input field with current value
- Min/max validation (0-100)
- Explanatory text about precedence logic
- Save button with success confirmation

## Best Practices

### Setting the Global Minimum

1. **Conservative Approach**: Set a reasonable global minimum (e.g., 5 units) to prevent stockouts
2. **Product-Specific Overrides**: Use product safety stock for high-value or critical items
3. **Monitor Logs**: Review blocked order logs to fine-tune settings

### Product Safety Stock Configuration

- **High-Priority Products**: Set specific safety stock (e.g., 10-20 units)
- **Standard Products**: Leave at 0 to use global minimum
- **Low-Stock Products**: Set lower than global if needed (e.g., 2 units)

## Troubleshooting

### Issue: Product with 0 safety stock cannot be sold

**Solution**: Check the global minimum setting. If it's too high, lower it or set a product-specific safety stock.

### Issue: Product with safety stock still using global minimum

**Cause**: Product safety stock is configured as 0 or NULL

**Solution**: Set a positive value for the product's safety stock

### Issue: Orders blocked despite sufficient inventory

**Check**:
1. Product safety stock value
2. Global minimum setting
3. Actual available inventory (`disponible`)
4. Review logs for detailed reason

## Related Documentation

- [Inventory System](./manual-inventario-por-zonas.md)
- [Zone Warehouses](./zone_warehouses.md)
- [Product Management](./manuales-usuario/README.md)

## Change Log

- **2026-01-14**: Initial implementation with configurable global minimum and precedence logic
