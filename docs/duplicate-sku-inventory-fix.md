# Duplicate SKU Inventory Fix

## Problem Description

Products with duplicate SKUs were not getting inventory synced properly. When the inventory synchronization job ran, it would only update the first product found with each SKU using `Product::where('sku', $sku)->first()`, leaving other products with the same SKU without inventory data.

## Solution Implemented

### 1. Fixed Inventory Sync Job

**File**: `app/Jobs/SyncProductInventory.php`

**Change**: Modified the sync process to find ALL products with the same SKU and update inventory for each of them.

```php
// Before (only synced first product)
$product = Product::where('sku', $sku)->first();

// After (syncs all products with same SKU)
$products = Product::where('sku', $sku)->get();
foreach ($products as $product) {
    // Update inventory for each product
}
```

### 2. Fix Command for Existing Data

**File**: `app/Console/Commands/FixDuplicateSkuInventory.php`

**Purpose**: Fix existing products that already have missing inventory due to the previous issue.

**Usage**:
```bash
# Check what would be fixed (dry run)
php artisan inventory:fix-duplicate-skus --dry-run

# Fix all duplicate SKU inventory issues
php artisan inventory:fix-duplicate-skus

# Fix specific SKU only
php artisan inventory:fix-duplicate-skus --sku=SPECIFIC_SKU_CODE
```

### 3. Enhanced Product Model

**File**: `app/Models/Product.php`

**New Methods**:

- `getProductsWithSameSku()`: Returns all products sharing the same SKU
- `getSharedInventoryForBodega($bodegaCode)`: Gets inventory from any product with the same SKU if this product has no inventory
- `scopeWithDuplicateSkus()`: Scope to find products with duplicate SKUs

**Usage Examples**:
```php
// Find all products with duplicate SKUs
$duplicateProducts = Product::withDuplicateSkus()->get();

// Get all products with same SKU
$sameSkuProducts = $product->getProductsWithSameSku();

// Get shared inventory (fallback to other products with same SKU)
$inventory = $product->getSharedInventoryForBodega('MDTAT');
```

## How It Works

### Inventory Sync Process
1. External system provides inventory data by SKU
2. System aggregates inventory totals by SKU
3. **NEW**: Finds ALL products with each SKU (not just first one)
4. **NEW**: Creates/updates inventory records for every product with that SKU
5. All products with same SKU now have identical inventory data

### Duplicate SKU Handling
- Products with same SKU share inventory data
- If one product runs out of inventory, others with same SKU are also unavailable
- Inventory decrements apply to all products with same SKU
- Safety stock rules apply consistently across duplicate SKUs

## Testing

### Finding Duplicate SKUs
```bash
# Check how many products have duplicate SKUs
php artisan tinker
>>> Product::withDuplicateSkus()->count()
>>> Product::select('sku')->groupBy('sku')->havingRaw('COUNT(*) > 1')->get()
```

### Testing Inventory Sync
```bash
# Manual sync trigger
php artisan tinker
>>> App\Jobs\SyncProductInventory::dispatch()

# Check if fix worked
php artisan inventory:fix-duplicate-skus --dry-run
```

## Current Status

**Duplicate SKUs Found**: 3 SKUs with duplicates
- `TRAAAR03EHDBLK` (2 products)
- `TRDR20RJBLK180` (2 products) 
- `TRAALR6ALB2` (2 products)

## Future Considerations

1. **Prevention**: Consider adding database constraints or validation to prevent duplicate SKUs if they shouldn't exist
2. **Monitoring**: Add monitoring to track when new duplicate SKUs are created
3. **Performance**: For large numbers of duplicate SKUs, consider batch processing optimizations
4. **Reporting**: Add admin dashboard section to view and manage duplicate SKU products

## Manual Resolution Steps

If you encounter the issue again:

1. **Immediate Fix**: Run the fix command
   ```bash
   php artisan inventory:fix-duplicate-skus
   ```

2. **Verify Fix**: Check that inventory sync is working
   ```bash
   php artisan inventory:fix-duplicate-skus --dry-run
   ```

3. **Monitor**: Watch for products with missing inventory and duplicate SKUs
   ```bash
   php artisan tinker
   >>> Product::withDuplicateSkus()->whereDoesntHave('inventories')->count()
   ```
