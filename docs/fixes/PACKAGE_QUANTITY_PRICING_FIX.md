# Package Quantity Pricing Fix

**Date:** January 22, 2026  
**Commit:** 381cb24

## Problem Summary

Two critical bugs in the package quantity pricing logic caused incorrect prices to be sent to the SOAP webservice for certain products.

## Bugs Fixed

### Bug #1: Wrong Price Stored in `order_products` Table

**Symptoms:**
- Orders showed "suspicious pricing" alerts (prices < $500)
- Products with `calculate_package_price = TRUE` had wrong prices in SOAP XML
- Example: Product with price=$882.35, package_qty=10
  - Expected SOAP unitPrice: $882.35
  - Actual SOAP unitPrice: $88.24 (divided twice!)

**Root Cause:**
The price stored in `order_products` table was sometimes the per-unit price instead of the package price, causing double division in SOAP generation.

**Fix:**
CartController now explicitly handles price storage based on `calculate_package_price` flag:
- When `TRUE`: Store package price (base × package_qty) → SOAP will divide
- When `FALSE`: Store per-unit price → SOAP will NOT divide

### Bug #2: Wrong Quantity When `calculate_package_price = FALSE`

**Symptoms:**
- Orders with `calculate_package_price = FALSE` sent wrong quantities to SOAP
- Example: Order 1 package of 5 units
  - Expected SOAP qty: 5
  - Actual SOAP qty: 1

**Root Cause:**
The `calculate_package_price` flag was controlling BOTH price division AND quantity multiplication. When FALSE, it set `effectivePackageQty = 1`, preventing quantity multiplication.

**Fix:**
The `calculate_package_price` flag now ONLY affects price division, NOT quantity multiplication:
- SOAP qty ALWAYS = order_quantity × package_quantity
- SOAP price division depends on the flag

## Expected Behavior

### Understanding `calculate_package_price` Flag

**When TRUE:** The product DB price is per-unit, and orders are for packages.
- Customer sees and orders packages
- SOAP receives individual units
- Example: Batteries sold in packs of 10

**When FALSE:** The product DB price is the package price, and the package is 1 orderable unit.
- Customer orders packages as single units
- SOAP receives packages as units
- Example: Promotional bundles like "Buy 5 Get 1 Free"

### Example 1: `calculate_package_price = TRUE`
```
Product Configuration:
- DB price: $882.35 per unit
- package_quantity: 10
- calculate_package_price: TRUE

Order: 12 packages

Stored in order_products:
- price: $8,823.50 (per-unit × package_qty)
- quantity: 12
- package_quantity: 10

SOAP XML:
- unitPrice: $882.35 ($8,823.50 ÷ 10)
- qty: 120 (12 × 10)
- Total: $105,882
```

### Example 2: `calculate_package_price = FALSE`
```
Product Configuration:
- DB price: $2,184.87 (package price)
- package_quantity: 5
- calculate_package_price: FALSE

Order: 2 packages

Stored in order_products:
- price: $2,184.87 (stored as-is)
- quantity: 2
- package_quantity: 5

SOAP XML:
- unitPrice: $2,184.87 (no division)
- qty: 2 (NO multiplication - package treated as 1 unit)
- Total: $4,369.74

Note: When FALSE, the package is treated as ONE orderable unit.
The SOAP qty is the number of packages ordered, NOT multiplied by package_quantity.
```

## Affected Orders

Orders created BEFORE this fix (commit 381cb24) may have incorrect pricing in their SOAP XML if they meet these criteria:

1. **Bug #1 affected orders:**
   - Products with `calculate_package_price = TRUE`
   - Stored price in `order_products` equals per-unit price (not package price)
   - Identifiable by "suspicious pricing" in audit report

2. **Bug #2 affected orders:**
   - Products with `calculate_package_price = FALSE`
   - SOAP qty doesn't match expected (order_qty × package_qty)

## Testing New Orders

After deploying this fix, test with these scenarios:

### Test Case 1: Package Price Product
```bash
# Product: calculate_package_price = TRUE, price = $882.35, package_qty = 10
# Order: 3 packages
# Expected SOAP: unitPrice = $882.35, qty = 30
php artisan orders:analyze-pricing-flow [order_id]
```

### Test Case 2: Non-Package Price Product
```bash
# Product: calculate_package_price = FALSE, price = $2,184.87, package_qty = 5
# Order: 2 packages
# Expected SOAP: unitPrice = $2,184.87, qty = 10
php artisan orders:analyze-pricing-flow [order_id]
```

## Related Commands

- `php artisan orders:analyze-pricing-flow {order_id}` - Analyze complete pricing flow
- `php artisan orders:debug-soap-pricing {order_id}` - Debug SOAP XML parsing
- `php artisan orders:daily-audit {date}` - Generate audit report with pricing checks

## Files Changed

- `app/Repositories/OrderRepository.php`
  - Simplified price division logic
  - Always multiply quantity by package_quantity

- `app/Http/Controllers/CartController.php`
  - Fixed price storage logic for all cases (regular, variations, bonifications, coupons)
  - Explicit handling based on `calculate_package_price` flag

## Migration Notes

**No database migration required.** The fix applies to NEW orders created after deployment.

Existing orders retain their original (potentially incorrect) pricing in the database, but future orders will be correct.

## Monitoring

After deployment, monitor the daily audit report:

```bash
php artisan orders:daily-audit
```

The "Suspicious Pricing" count should drop significantly if the fix is working correctly.
