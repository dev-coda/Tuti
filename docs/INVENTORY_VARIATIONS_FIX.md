# Product Variations & Inventory System

## Overview

This document explains how the inventory system works for products with variations and documents the fixes implemented to ensure proper inventory handling.

## Problem Statement

Users reported that when the inventory system is enabled, it was impossible to order products with variations, even when the parent product showed available inventory in the admin panel. The variations appeared to have no inventory.

## How Variations Work

### Product Structure

1. **Parent Product**: A product with `variation_id` set (e.g., a T-shirt with "Size" variation)
2. **Variation**: The type of variation (e.g., "Size", "Color") - stored in `variations` table
3. **Variation Items**: The specific options (e.g., "Small", "Medium", "Large") - stored in `variation_items` table
4. **Product-Variation Link**: The `product_item_variation` pivot table links products to their variation items

### Inventory Architecture

**CRITICAL**: Inventory is **ALWAYS** stored at the **parent product level**, never at the variation item level.

```
Product (ID: 123, Name: "T-Shirt", variation_id: 1)
├── VariationItem "Small" (ID: 10)
├── VariationItem "Medium" (ID: 11)
└── VariationItem "Large" (ID: 12)

ProductInventory (product_id: 123, bodega_code: "MDTAT", available: 100)
   ↑
   All three variation items share this inventory pool
```

### Why This Design?

This design allows:

-   **Shared Inventory**: All sizes share the same inventory pool (e.g., 100 T-shirts total across all sizes)
-   **Flexible Management**: Admins manage inventory once at the parent level
-   **Accurate Tracking**: Inventory decrements from a single source of truth

## Inventory Management Control for Variations

As of November 2025, products with variations now support the standard `inventory_opt_out` setting. This means:

-   **By default**, products with variations WILL have inventory managed (if global inventory is enabled)
-   **To disable inventory tracking** for a product with variations, check the "Excluir de gestión de inventario" checkbox in the product edit form
-   This gives admins full control over which variation products track inventory and which don't

### When to Exclude Variations from Inventory

Consider disabling inventory management for:
- Products with unlimited availability
- Digital products or services
- Products where you want to always allow orders regardless of stock
- Pre-order items

### When to Enable Inventory for Variations

Keep inventory management enabled for:
- Physical products with limited stock
- Products where you need to track availability per warehouse
- Products that need safety stock thresholds

## The Fix

### Changes Made

#### 1. CartController.php - Add to Cart Method (Lines 354-380)

**Added:**

-   Clear comments explaining that variation products use parent inventory
-   Detailed logging when products are blocked due to safety stock
-   Logs include whether the product has variations and which variation item was selected

**Purpose**: Help debug inventory issues and clarify that all checks use parent product inventory.

#### 2. CartController.php - Process Order Pre-Check (Lines 589-642)

**Added:**

-   Comments clarifying that inventory checks are at the parent product level
-   Note that `cartItem['product_id']` is always the parent product ID
-   Comprehensive logging for all three inventory failure scenarios:
    -   Below safety stock
    -   Low inventory (<= 5 units)
    -   Quantity exceeds available

**Purpose**: Ensure developers understand that even when `cartItem['variation_id']` is set, the inventory check always uses the parent product.

#### 3. CartController.php - Inventory Decrement (Lines 829-873)

**Added:**

-   Clear documentation that inventory is decremented from parent product
-   Note that `$p->id` is the parent product ID, regardless of variation selection
-   Error logging with full context when orders are rolled back
-   Warning logging when inventory records need to be created during order

**Purpose**: Make it crystal clear that inventory updates always target the parent product, and log any issues for debugging.

#### 4. Product Model - Documentation (Lines 100-121)

**Added:**

-   PHPDoc comments on `inventories()` relationship explaining variation inventory architecture
-   PHPDoc comments on `getInventoryForBodega()` clarifying it returns shared inventory for variations
-   Warning comment: "Never look for inventory on individual variation items"

**Purpose**: Document the architecture in the model itself so future developers understand the design.

### What Was NOT Changed

The actual logic for checking inventory was already correct! The code was always using the parent product's inventory. The issue was likely:

1. **Lack of clarity**: Developers might have tried to "fix" it by looking for variation-level inventory
2. **Debugging difficulty**: No logging made it hard to understand why orders were blocked
3. **Documentation**: No clear explanation of how variations and inventory interact

## How It Works Now

### When Adding to Cart

1. User selects a product with variations (e.g., T-Shirt)
2. User selects a variation item (e.g., "Medium")
3. System checks inventory on the **parent product** (T-Shirt)
4. If inventory is sufficient, item is added to cart with:
    - `product_id`: Parent product ID
    - `variation_id`: Selected variation item ID (e.g., "Medium")

### When Processing Order

1. For each cart item, system loads the product using `product_id` (parent product)
2. Checks inventory using `ProductInventory` where `product_id = parent_product_id`
3. If all checks pass, decrements inventory from the parent product
4. Saves order with `OrderProduct` record linking:
    - `product_id`: Parent product ID
    - `variation_item_id`: The specific variation chosen

### Inventory Display

In all views (product pages, cart, product lists):

-   `$product->getInventoryForBodega($bodegaCode)` is called
-   This returns inventory from the parent product's `product_inventories` records
-   All variation items show the same inventory (because they share it)

## Logging Added

All inventory-related failures now log to `storage/logs/laravel.log` with:

### Add to Cart Logging

```php
'product_id' => Product ID
'product_name' => Product name
'has_variation' => true/false
'variation_id_selected' => The variation item ID selected
'available' => Current available inventory
'safety' => Safety stock threshold
'bodega' => Warehouse code
```

### Order Processing Logging

**Pre-check failures:**

-   "Order blocked: product below safety stock"
-   "Order blocked: low inventory"
-   "Order blocked: quantity exceeds available"

**Final check failures:**

-   "Order rollback: inventory insufficient during final check"

**Warnings:**

-   "Creating new inventory record during order" (shouldn't happen normally)

## Troubleshooting

### "Product blocked due to safety stock"

**Check:**

1. View log entry to see actual available vs safety stock
2. Verify inventory in admin: `/products/{id}/edit` shows inventory by warehouse
3. Check if safety stock is too high: Product edit page or category safety stock
4. Ensure product has inventory record for user's warehouse (bodega)

### "Product appears to have no inventory"

**Likely causes:**

1. No `product_inventories` record exists for that warehouse
2. Inventory is set to 0 or below safety stock
3. User's zone is not mapped to a warehouse

**Fix:**

1. Create inventory record for the product in the correct warehouse
2. Ensure `available` > `safety_stock`
3. Check `zone_warehouses` table for zone-to-bodega mapping

### Variation items showing different inventory

**This should NOT happen**. If it does:

1. Check that all variation items point to the same parent product ID
2. Verify there are no duplicate products with different IDs
3. Ensure SKUs are not being used incorrectly to create separate inventory pools

## Database Schema Reference

### Tables Involved

**products**

-   `id`: Primary key
-   `variation_id`: FK to `variations` (NULL if no variation)
-   `sku`: Stock keeping unit
-   Other product fields...

**product_item_variation** (pivot table)

-   `product_id`: FK to parent product
-   `variation_item_id`: FK to variation item
-   `price`: Price for this variation
-   `sku`: SKU for this variation (optional)
-   `enabled`: Whether this variation is available

**product_inventories**

-   `id`: Primary key
-   `product_id`: FK to product (ALWAYS the parent product)
-   `bodega_code`: Warehouse code
-   `available`: Available quantity
-   `physical`: Physical count
-   `reserved`: Reserved quantity

## Best Practices

### For Developers

1. **Never** create inventory records for variation items
2. **Always** use the parent product ID when checking/updating inventory
3. **Always** check logs when debugging inventory issues
4. **Document** any changes to inventory logic

### For Admins

1. Set inventory on the parent product, not individual variations
2. Use safety stock to prevent selling below desired threshold
3. Ensure all products have inventory records for all warehouses they should be available in
4. Monitor logs for inventory-related errors

## Related Files

-   `app/Http/Controllers/CartController.php`: Cart and order processing logic
-   `app/Models/Product.php`: Product model with inventory methods
-   `app/Models/ProductInventory.php`: Inventory model
-   `app/Models/Variation.php`: Variation model
-   `app/Models/VariationItem.php`: Variation item model

## Testing Checklist

To verify variations work correctly:

-   [ ] Create a product with variations (e.g., T-shirt with sizes S, M, L)
-   [ ] Add inventory to the parent product for a specific warehouse
-   [ ] Enable inventory system in settings
-   [ ] As a user in that warehouse's zone:
    -   [ ] View the product - should show inventory
    -   [ ] Add each variation to cart - should succeed
    -   [ ] Complete checkout - should succeed
    -   [ ] Verify inventory was decremented from parent product
-   [ ] Check logs - should see detailed entries if any issues occur

## Version History

-   **2025-11-20**: Allow inventory management for products with variations
    -   Removed automatic exclusion of products with variations from inventory management
    -   Products with variations now respect the `inventory_opt_out` checkbox setting
    -   Admins can now choose whether to manage inventory for products with variations on a per-product basis
    -   Updated documentation to reflect this change

-   **2025-10-30**: Initial fix - Added comprehensive logging and documentation
    -   Clarified that inventory is always at parent product level
    -   Added logging for all inventory-related failures
    -   Documented variation inventory architecture in code comments

---

**Note**: Products with variations can now have inventory managed if desired. Use the "Excluir de gestión de inventario" checkbox in the product edit form to control this behavior for each product individually.
