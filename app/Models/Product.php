<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Setting;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'short_description',
        'technical_specifications',
        'warranty',
        'other_information',
        'sku',
        'slug',
        'active',
        'price',
        'delivery_days',
        'discount',
        'discount_type',
        'first_purchase_only',
        'quantity_min',
        'quantity_max',
        'step',
        'tax_id',
        'brand_id',
        'variation_id',
        'is_combined',
        'parent_id',
        'package_quantity',
        'calculate_package_price',
        'safety_stock',
        'inventory_opt_out',
        'sales_count',
    ];


    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function labels()
    {
        return $this->belongsToMany(Label::class)->whereActive(1);
    }

    public function related()
    {
        return $this->belongsToMany(Product::class, 'product_related', 'product_id', 'product_related_id')->orderBy('name');
    }


    public function tax()
    {
        return $this->belongsTo(Tax::class);
    }

    public function taxValue()
    {
        return $this->tax->tax;
    }

    public function variation()
    {
        return $this->belongsTo(Variation::class);
    }


    public function combinations()
    {
        return $this->belongsToMany(Product::class, 'product_combination', 'parent_id', 'product_id')
            ->orderBy('name')
            ->withPivot(["price", "variation_item_id"]);
    }


    public function items()
    {
        return $this->belongsToMany(VariationItem::class, 'product_item_variation', 'product_id', 'variation_item_id')
            ->withPivot(["price", "enabled", 'sku']);
    }


    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Get inventory records for this product
     * NOTE: For products with variations, inventory is ALWAYS stored at the parent product level.
     * All variation items (e.g., Small, Medium, Large) share the same inventory pool.
     * Never look for inventory on individual variation items.
     */
    public function inventories()
    {
        return $this->hasMany(ProductInventory::class);
    }

    /**
     * Get available inventory for a specific warehouse (bodega)
     * For products with variations, this returns the shared inventory pool
     * that all variation items draw from.
     */
    public function getInventoryForBodega(?string $bodegaCode): int
    {
        if (!$bodegaCode) return 0;
        $inv = $this->inventories->firstWhere('bodega_code', $bodegaCode);
        return $inv?->available ?? 0;
    }

    public function getInventoryForMdtat(): int
    {
        $inv = $this->inventories->firstWhere('bodega_code', 'MDTAT');
        return $inv?->available ?? 0;
    }

    /**
     * Get all products that share the same SKU as this product
     */
    public function getProductsWithSameSku()
    {
        if (empty($this->sku)) {
            return collect([$this]);
        }

        return Product::where('sku', $this->sku)->get();
    }

    /**
     * Get the shared inventory for this SKU across all products with the same SKU
     * Useful for duplicate SKUs that should share inventory
     */
    public function getSharedInventoryForBodega(?string $bodegaCode): int
    {
        if (!$bodegaCode || empty($this->sku)) {
            return $this->getInventoryForBodega($bodegaCode);
        }

        // First try to get inventory from this product
        $inventory = $this->getInventoryForBodega($bodegaCode);

        // If this product has no inventory, try to get it from other products with same SKU
        if ($inventory === 0) {
            $productsWithSameSku = $this->getProductsWithSameSku();
            foreach ($productsWithSameSku as $product) {
                if ($product->id !== $this->id) {
                    $sharedInventory = $product->getInventoryForBodega($bodegaCode);
                    if ($sharedInventory > 0) {
                        return $sharedInventory;
                    }
                }
            }
        }

        return $inventory;
    }

    public function getEffectiveSafetyStock(): int
    {
        // Product-specific overrides category; otherwise use category safety or 0
        if (!is_null($this->safety_stock)) {
            return (int) $this->safety_stock;
        }
        $category = $this->categories->first();
        return (int) ($category?->safety_stock ?? 0);
    }

    public function bonifications()
    {
        return $this->belongsToMany(Bonification::class);
    }

    public function getImageAttribute()
    {
        return $this->images->first()?->path;
    }

    public function getFinalPriceForUser($has_orders = false, $vendorCartTotal = null)
    {
        $discount = 0;
        $discount_on = false;

        // Check if first purchase discount restrictions are enabled via .env
        $enforce_first_purchase = config('app.enforce_first_purchase_discounts', true);

        // Product discount
        if ($this->discount > 0) {
            // Apply product discount if:
            // 1. Enforce is disabled, OR
            // 2. User has no orders, OR  
            // 3. User has orders but first_purchase_only is false
            if (!$enforce_first_purchase || !$has_orders || !$this->first_purchase_only) {
                $discount = $this->discount;
                $discount_on = 'Producto';
            }
        }

        // Brand discount (higher priority than product)
        if ($this->brand && $this->brand->discount > $discount) {
            if (!$enforce_first_purchase || !$has_orders || !$this->brand->first_purchase_only) {
                $discount = $this->brand->discount;
                $discount_on = 'Marca';
            }
        }

        // Vendor discount (highest priority) - with minimum amount check
        if ($this->brand && $this->brand->vendor && $this->brand->vendor->discount >= $discount) {
            $vendor = $this->brand->vendor;

            // Check if vendor discount should apply based on first purchase rules
            $vendorDiscountApplies = !$enforce_first_purchase || !$has_orders || !$vendor->first_purchase_only;

            // Check if vendor minimum discount amount is met
            $vendorMinimumMet = true;
            if ($vendor->minimum_discount_amount > 0) {
                if ($vendorCartTotal !== null) {
                    // In cart context: check the total cart amount for this vendor
                    $vendorMinimumMet = $vendorCartTotal >= $vendor->minimum_discount_amount;
                } else {
                    // In catalog/home context: check if this individual product's price meets the minimum
                    // Calculate the product's total price (with tax and package quantity)
                    $price = $this->price;
                    $variation = $this->items?->first();
                    if ($variation) {
                        $price = $variation->pivot->price;
                    }
                    $packageQuantity = $this->package_quantity ?? 1;
                    $priceWithTax = $this->taxValue() > 0 ? ($price + ($price * $this->taxValue() / 100)) : $price;
                    $totalProductPrice = $priceWithTax * $packageQuantity;

                    // Only apply discount if product price meets minimum
                    $vendorMinimumMet = $totalProductPrice >= $vendor->minimum_discount_amount;
                }
            }

            if ($vendorDiscountApplies && $vendorMinimumMet) {
                $discount = $vendor->discount;
                $discount_on = 'Vendor';
            }
        }

        // Bonifications override all discounts
        if ($this->bonifications->count()) {
            $discount_on = false;
            $discount = 0;
        }

        $price = $this->price;

        $variation = $this->items?->first();
        if ($this->items?->first()) {
            $price = $variation->pivot->price;
        }

        $has_discount = false;
        if ($discount > 0) {
            $has_discount = true;
        }

        $discountedPrice = $price - ($price * $discount / 100);
        $finalPrice = $this->taxValue() > 0 ? ($discountedPrice + ($discountedPrice * $this->taxValue() / 100)) : $discountedPrice;

        $packageQuantity = $this->package_quantity ?? 1;

        $finalPriceWithPackage = $finalPrice * $packageQuantity;
        $pricePreDiscount = $price + ($price * $this->taxValue() / 100);

        return [
            'old' => $pricePreDiscount * $packageQuantity,
            'price' => $finalPriceWithPackage,
            'totalDiscount' => ($price * $discount / 100) * $packageQuantity,
            'discount' => $discount,
            'discount_on' => $discount_on,
            'has_discount' => $has_discount,
            'originalPrice' => $price * $packageQuantity,
            'perItemPrice' => $finalPrice,
        ];
    }

    /**
     * Get final price considering coupon discounts
     * This method integrates with the CouponDiscountService results
     */
    public function getFinalPriceWithCoupon($has_orders = false, $couponData = null): array
    {
        $basePriceInfo = $this->getFinalPriceForUser($has_orders);

        // If no coupon data provided, return base pricing
        if (!$couponData) {
            return $basePriceInfo;
        }

        // Apply coupon-modified pricing
        $discountType = $couponData['applied_discount_type'] ?? 'percentage';
        
        if ($discountType === 'fixed_amount') {
            // For fixed amount coupons, use the modified unit price
            $newUnitPrice = $couponData['new_unit_price'];
            $packageQuantity = $this->package_quantity ?? 1;
            $finalPriceWithPackage = $newUnitPrice * $packageQuantity;

            // Calculate tax if applicable
            $finalPriceWithTax = $this->taxValue() > 0
                ? ($finalPriceWithPackage + ($finalPriceWithPackage * $this->taxValue() / 100))
                : $finalPriceWithPackage;

            $originalPrice = $couponData['base_price'] * $packageQuantity;
            $originalPriceWithTax = $this->taxValue() > 0
                ? ($originalPrice + ($originalPrice * $this->taxValue() / 100))
                : $originalPrice;

            return [
                'old' => $originalPriceWithTax,
                'price' => $finalPriceWithTax,
                'totalDiscount' => $couponData['final_discount_amount'],
                'discount' => 0, // Don't use percentage field for fixed amount
                'discount_on' => 'Cupón (Monto Fijo)',
                'has_discount' => true,
                'originalPrice' => $originalPrice,
                'perItemPrice' => $newUnitPrice,
                'coupon_applied' => true,
                'coupon_type' => 'fixed_amount'
            ];
        } else {
            // For percentage coupons or when existing discount is better
            $appliedPercentage = $couponData['applied_discount_percentage'];
            $discountSource = $couponData['discount_source'] ?? 'existing';

            // Use the applied percentage (which is the higher of coupon vs existing)
            $price = $couponData['base_price'];
            $packageQuantity = $this->package_quantity ?? 1;

            $discountedPrice = $price - ($price * $appliedPercentage / 100);
            $finalPrice = $this->taxValue() > 0
                ? ($discountedPrice + ($discountedPrice * $this->taxValue() / 100))
                : $discountedPrice;

            $finalPriceWithPackage = $finalPrice * $packageQuantity;
            $pricePreDiscount = $price + ($price * $this->taxValue() / 100);

            $discountLabel = $discountSource === 'coupon' ? 'Cupón' : $basePriceInfo['discount_on'];

            return [
                'old' => $pricePreDiscount * $packageQuantity,
                'price' => $finalPriceWithPackage,
                'totalDiscount' => ($price * $appliedPercentage / 100) * $packageQuantity,
                'discount' => $appliedPercentage,
                'discount_on' => $discountLabel,
                'has_discount' => $appliedPercentage > 0,
                'originalPrice' => $price * $packageQuantity,
                'perItemPrice' => $finalPrice,
                'coupon_applied' => $discountSource === 'coupon',
                'coupon_type' => 'percentage'
            ];
        }
    }

    public function getFinalPriceAttribute()
    {
        // For backward compatibility, call the new method with has_orders = false
        // This means all discounts are applied by default when using the attribute directly
        return $this->getFinalPriceForUser(false);
    }

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_products');
    }


    public function getCategoryAttribute()
    {
        return $this->categories->first();
    }

    public function isInventoryManaged(): bool
    {
        // Global toggle
        $inventoryEnabled = Setting::getByKey('inventory_enabled');
        $globalOn = ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true);
        if (!$globalOn) {
            return false;
        }

        // Product-level opt-out (optional column)
        if (!empty($this->inventory_opt_out) && (int)$this->inventory_opt_out === 1) {
            return false;
        }

        // Category-level opt-out (optional column) or default by name 'OFERTAS'
        $category = $this->categories->first();
        if ($category) {
            if (!empty($category->inventory_opt_out) && (int)$category->inventory_opt_out === 1) {
                return false;
            }
            if (mb_strtoupper(trim((string) $category->name)) === 'OFERTAS') {
                return false;
            }
        }

        // Products with variations now respect the inventory_opt_out setting
        // instead of being excluded by default. This allows users to control
        // whether variations have inventory managed on a per-product basis.

        return true;
    }

    public function highlights()
    {
        return $this->hasMany(ProductHighlight::class);
    }

    /**
     * Increment the sales count for this product
     */
    public function incrementSalesCount(int $quantity = 1): void
    {
        $this->increment('sales_count', $quantity);
    }

    /**
     * Scope for best-selling products
     */
    public function scopeBestSelling($query)
    {
        return $query->orderBy('sales_count', 'desc');
    }

    /**
     * Scope for products that have duplicate SKUs
     */
    public function scopeWithDuplicateSkus($query)
    {
        return $query->whereIn('sku', function ($subQuery) {
            $subQuery->select('sku')
                ->from('products')
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->groupBy('sku')
                ->havingRaw('COUNT(*) > 1');
        });
    }
}
