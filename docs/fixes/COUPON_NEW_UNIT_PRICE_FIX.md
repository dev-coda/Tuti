# Coupon `new_unit_price` Missing Key Fix

## Issue
When processing percentage coupons multiple times, the system threw an error:
```
Undefined array key "new_unit_price"
```

## Root Cause
The `CouponDiscountService` was returning `new_unit_price` for:
- ✅ Fixed amount coupons
- ✅ Non-applicable products (keeping existing discounts)
- ❌ **Percentage coupons** ← Missing!

The `CartController` expected `new_unit_price` to be present in all coupon response scenarios (lines 1169, 1473, 1513), causing errors when percentage coupons were used.

## Fix Applied

### File: `app/Services/CouponDiscountService.php`

**Lines 139-156** (percentage coupon logic):

```php
// Calculate the new unit price after percentage discount
$newUnitPrice = $basePrice - ($basePrice * $finalDiscountPercentage / 100);

$modifiedProducts[] = [
    'product_id' => $product->id,
    'variation_id' => $cartItem['variation_id'] ?? null,
    'quantity' => $quantity,
    'base_price' => $basePrice,
    'new_unit_price' => $newUnitPrice,  // ✅ Now included
    'package_quantity' => $packageQuantity,
    'applied_discount_type' => 'percentage',
    'applied_discount_percentage' => $finalDiscountPercentage,
    // ... other fields
];
```

## Result
- ✅ Percentage coupons can now be processed multiple times without errors
- ✅ Consistent return structure across all coupon discount types
- ✅ `CartController` can safely access `new_unit_price` for vendor total calculations

## Deployment
```bash
cd /var/www/html/tuti
git pull origin master
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

## Testing
1. Create an order with a percentage coupon
2. Apply the same coupon to multiple orders
3. Verify no "Undefined array key" errors occur
4. Verify pricing calculations are correct in vendor totals

---
**Fixed:** 2026-01-22  
**Related Files:** `app/Services/CouponDiscountService.php`, `app/Http/Controllers/CartController.php`
