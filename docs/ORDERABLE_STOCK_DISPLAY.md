# Orderable Stock Display System

## Overview

All client-facing inventory displays now show **orderable stock** instead of raw available inventory. This prevents confusion where users see "6 units available" but can only order 3 (due to safety stock restrictions).

## Problem Solved

**Before:**
- Frontend displayed: "Inventario: 6"
- User adds 6 to cart
- System blocks order: "Inventario insuficiente" (safety stock = 3)
- User confused: "Why can't I order what you're showing me?"

**After:**
- Frontend displays: "Inventario: 3" (6 available - 3 safety = 3 orderable)
- User adds 3 to cart
- Order processes successfully
- Clear, accurate expectations ✅

## Implementation

### Product Model Methods

Added two new helper methods to calculate orderable stock:

```php
// app/Models/Product.php

/**
 * Get orderable stock for bodega (available - safety stock)
 * This is what should be shown to clients in frontend views
 */
public function getOrderableStockForBodega(?string $bodegaCode): int
{
    $available = $this->getInventoryForBodega($bodegaCode);
    $safety = (int) $this->getEffectiveSafetyStock();
    $globalMin = (int) (Setting::getByKey('global_minimum_inventory') ?? 5);
    $effectiveMinimum = ($safety > 0) ? $safety : $globalMin;
    
    return max(0, $available - $effectiveMinimum);
}

/**
 * Get orderable stock for MDTAT (available - safety stock)
 * This is what should be shown to clients in frontend views
 */
public function getOrderableStockForMdtat(): int
{
    $available = $this->getInventoryForMdtat();
    $safety = (int) $this->getEffectiveSafetyStock();
    $globalMin = (int) (Setting::getByKey('global_minimum_inventory') ?? 5);
    $effectiveMinimum = ($safety > 0) ? $safety : $globalMin;
    
    return max(0, $available - $effectiveMinimum);
}
```

### Frontend Views Updated

#### 1. Product Card Component (`resources/views/components/product.blade.php`)

**Before:**
```php
$available = $product->getInventoryForBodega($bodegaCode);
<p>Inventario: {{ $available }}</p>
```

**After:**
```php
$orderableStock = $product->getOrderableStockForBodega($bodegaCode);
<p>Inventario: {{ $orderableStock }}</p>
```

#### 2. Single Product Page (`resources/views/pages/product.blade.php`)

**Before:**
```php
$available = auth()->check() ? $product->getInventoryForBodega($bodegaCode) : $product->getInventoryForMdtat();
@if($available < 10 && $available > 0)
    <span>últimas unidades disponibles</span>
@endif
```

**After:**
```php
$orderableStock = auth()->check() 
    ? $product->getOrderableStockForBodega($bodegaCode) 
    : $product->getOrderableStockForMdtat();
@if($orderableStock < 10 && $orderableStock > 0)
    <span>últimas unidades disponibles</span>
@endif
```

### API Endpoints Updated

All inventory API endpoints now return both values:

```json
{
  "product_id": 123,
  "bodega_code": "MDTAT",
  "available": 35,           // Raw available (for admin/reporting)
  "orderable_stock": 30,     // What clients can actually order (35 - 5 safety)
  "physical": 78,
  "reserved": 43,
  "safety_stock": 5
}
```

**API Endpoints:**
- `GET /api/inventarios` - List all inventory
- `GET /api/inventarios/producto/{product}` - Product inventory details
- `GET /api/inventarios/bodega/{bodegaCode}` - Inventory by warehouse

### Calculation Logic

```
Orderable Stock = Available - Effective Minimum

Where:
- Available = físico - reservado (from database)
- Effective Minimum = product safety stock (if > 0) OR global minimum
```

### Examples

| Raw Available | Safety Stock | Global Min | Orderable Shown | Explanation |
|--------------|--------------|------------|-----------------|-------------|
| 30 | 10 | 5 | **20** | 30 - 10 = 20 |
| 30 | 3 | 5 | **27** | 30 - 3 = 27 (product safety wins) |
| 30 | 0 | 5 | **25** | 30 - 5 = 25 (global min used) |
| 8 | 10 | 5 | **0** | max(0, 8 - 10) = 0 (never negative) |

## Client-Facing Views

### Where Orderable Stock is Displayed

1. **Product Cards (Catalog/Category Pages)**
   - Shows: "Inventario: X"
   - Color: Green if > 5, Red if ≤ 5

2. **Single Product Page**
   - Shows: "últimas unidades disponibles" badge when < 10
   - Shows: "Producto no disponible" when 0

3. **API Responses (Mobile Apps/External Integrations)**
   - Returns both `available` and `orderable_stock` fields

### Admin Views (Not Modified)

Admin inventory views continue to show raw `available`, `physical`, and `reserved` for management and reporting purposes:

- Settings → Inventory Management
- Product Edit → Inventory Tab
- Reports → Inventory Reports
- Admin API responses include both values

## Testing

### Test Scenarios

1. **Product with Safety Stock = 10, Available = 35**
   - ✅ Frontend shows: "Inventario: 25"
   - ✅ User can order up to 25 units
   - ✅ Order processing succeeds

2. **Product with Safety Stock = 0, Available = 12, Global Min = 5**
   - ✅ Frontend shows: "Inventario: 7"
   - ✅ Global minimum applies
   - ✅ Order processing succeeds

3. **Product with Safety Stock = 3, Available = 6**
   - ✅ Frontend shows: "Inventario: 3"
   - ✅ "últimas unidades disponibles" badge appears
   - ✅ Order processing succeeds

4. **Product with Safety Stock = 5, Available = 3**
   - ✅ Frontend shows: "Producto no disponible"
   - ✅ Product appears out of stock (orderable = 0)
   - ✅ Cannot add to cart

## Migration Notes

### For Existing Deployments

1. No database migrations required
2. No cache clearing required (uses existing methods)
3. Changes are backward compatible
4. API responses include new `orderable_stock` field without breaking existing integrations

### User Communication

Inform sellers that:
- Inventory counts now reflect **orderable quantities**
- This prevents "false positive" inventory displays
- Safety stock is automatically accounted for
- No action required on their part

## Related Documentation

- [Global Inventory Minimum System](./GLOBAL_INVENTORY_MINIMUM.md)
- [Inventory Management](./manual-inventario-por-zonas.md)
- [API Documentation](./api-documentation.md)

## Change Log

- **2026-01-14**: Initial implementation of orderable stock display system
  - Added `getOrderableStockForBodega()` and `getOrderableStockForMdtat()` methods
  - Updated product card component
  - Updated single product page
  - Enhanced API responses with `orderable_stock` field
