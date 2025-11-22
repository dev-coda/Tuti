# Fix: Inventory Opt-Out Not Working in Product Display

**Date:** November 21, 2025  
**Status:** ✅ Fixed  
**Severity:** High - Products excluded from inventory showing as unavailable

## Problem

Products with "Excluir de gestión de inventario" (inventory_opt_out) checked were still showing as "Producto no disponible para tu ubicación" even though they should ALWAYS be available for purchase when excluded from inventory management.

## Root Cause

The product views were checking inventory availability WITHOUT first checking if the product was excluded from inventory management.

**Problematic logic:**
```php
@if($showInventory && $available <= 0)
    <p>Producto no disponible para tu ubicación</p>
@endif
```

This showed "unavailable" for ALL products with 0 inventory, even if they had `inventory_opt_out = 1`.

## The Fix

### Correct Logic Flow

Products excluded from inventory (`inventory_opt_out = 1` OR in OFERTAS category OR has variations with opt-out) should:
1. ✅ Skip all inventory checks
2. ✅ Never show "unavailable" message
3. ✅ Never show inventory count
4. ✅ Always be purchasable

### Files Changed

#### 1. resources/views/pages/product.blade.php

**Before:**
```php
@if($showInventory && $available <= 0)
    <p>Producto no disponible para tu ubicación</p>
@endif
```

**After:**
```php
@php
    $isManaged = $product->isInventoryManaged();
@endphp
@if($showInventory && $isManaged && $available <= 0)
    <p>Producto no disponible para tu ubicación</p>
@endif
```

**Changes:**
- Added `$isManaged` check
- Only show unavailable message if product IS managed AND inventory is 0
- Only show inventory counts if product IS managed
- Only show "últimas unidades" tag if product IS managed

#### 2. resources/views/components/product.blade.php

**Before:**
```php
@if($showInventory)
    @if($available <= 0)
        <p>Producto no disponible para tu ubicación</p>
    @endif
@endif
```

**After:**
```php
@php
    $isManaged = $product->isInventoryManaged();
@endphp
@if($showInventory && $isManaged)
    @if($available <= 0)
        <p>Producto no disponible para tu ubicación</p>
    @endif
@endif
```

## How It Works Now

### Product WITH Inventory Management (inventory_opt_out = 0)

**Scenario 1:** Available = 100
- ✅ Shows: "Inventario: 100" (green)
- ✅ Can add to cart

**Scenario 2:** Available = 3
- ✅ Shows: "Inventario: 3" (red) + "últimas unidades disponibles" tag
- ✅ Can add to cart

**Scenario 3:** Available = 0
- ✅ Shows: "Producto no disponible para tu ubicación" (red)
- ❌ Cannot add to cart

### Product WITHOUT Inventory Management (inventory_opt_out = 1)

**Any Scenario:**
- ✅ Shows: No inventory message at all
- ✅ **ALWAYS can add to cart** (regardless of actual inventory)
- ✅ No "unavailable" message ever

## What Triggers Inventory Opt-Out

A product is excluded from inventory management if:

1. **Product-level opt-out:** `product.inventory_opt_out = 1`
2. **Category-level opt-out:** `category.inventory_opt_out = 1`
3. **OFERTAS category:** Category name is "OFERTAS" (case-insensitive)
4. **Global disabled:** `inventory_enabled = 0` in settings

This is determined by `Product::isInventoryManaged()` method.

## Testing

### Test Case 1: Product with inventory_opt_out = 1

1. Edit a product
2. Check "Excluir de gestión de inventario"
3. Set inventory to 0 (or don't add any inventory)
4. Save product
5. View product as customer
6. **Expected:** No "unavailable" message, can add to cart

### Test Case 2: Product with inventory_opt_out = 0 and inventory = 0

1. Edit a product
2. Uncheck "Excluir de gestión de inventario"  
3. Set inventory to 0
4. Save product
5. View product as customer
6. **Expected:** "Producto no disponible para tu ubicación", cannot add to cart

### Test Case 3: OFERTAS category

1. Assign product to "OFERTAS" category
2. Set inventory to 0
3. View product as customer
4. **Expected:** No "unavailable" message, can add to cart (auto-excluded)

### Test Case 4: Product with variations and opt-out

1. Create product with variations (e.g., sizes)
2. Check "Excluir de gestión de inventario"
3. Don't add any inventory
4. View product as customer
5. **Expected:** No "unavailable" message, can add to cart

## Behavior Matrix

| Inventory Enabled | Inventory Opt-Out | Available Qty | Shows Inventory? | Shows Unavailable? | Can Add to Cart? |
|-------------------|-------------------|---------------|------------------|---------------------|------------------|
| ✅ Yes | ❌ No (managed) | 100 | ✅ Yes | ❌ No | ✅ Yes |
| ✅ Yes | ❌ No (managed) | 0 | ✅ Yes | ✅ Yes | ❌ No |
| ✅ Yes | ✅ Yes (excluded) | 100 | ❌ No | ❌ No | ✅ Yes |
| ✅ Yes | ✅ Yes (excluded) | 0 | ❌ No | ❌ No | ✅ Yes |
| ❌ No | N/A | Any | ❌ No | ❌ No | ✅ Yes |

## Related Code

### Product::isInventoryManaged()
```php
public function isInventoryManaged(): bool
{
    // Check global toggle
    if (!$inventoryEnabled) return false;
    
    // Check product-level opt-out
    if ($this->inventory_opt_out == 1) return false;
    
    // Check category-level opt-out
    if ($category->inventory_opt_out == 1) return false;
    if ($category->name == 'OFERTAS') return false;
    
    return true;
}
```

### CartController::add()
The cart controller already respects `isInventoryManaged()`:
```php
if ($isInventoryEnabled && $product->isInventoryManaged()) {
    // Check inventory
} else {
    // Skip inventory checks - allow add to cart
}
```

## Impact

### Positive
- ✅ Products excluded from inventory are now always available
- ✅ Consistent behavior between views and cart logic
- ✅ No more confusion about "unavailable" products that should be available
- ✅ Proper support for unlimited-stock products
- ✅ Proper support for OFERTAS category

### Negative
- None - this fixes broken functionality

## Prevention

When creating new product views or components:

**❌ DON'T:**
```php
@if($available <= 0)
    <p>Unavailable</p>
@endif
```

**✅ DO:**
```php
@if($product->isInventoryManaged() && $available <= 0)
    <p>Unavailable</p>
@endif
```

**Always check `isInventoryManaged()` before checking inventory!**

## Related Issues Fixed

This also fixes:
- Products with variations showing unavailable when they should be excluded
- OFERTAS category products showing unavailable
- Category-level inventory opt-out not working in display

## Documentation

- See `VARIATION_INVENTORY_OPT_OUT_FIX.md` for variation inventory behavior
- See `docs/manual-inventario-por-zonas.md` for complete inventory system docs

---

**Tested:** Local ✅  
**Ready for Production:** ✅  
**Breaking Changes:** None  
**Migration Required:** No

