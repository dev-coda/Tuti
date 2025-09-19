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

    public function inventories()
    {
        return $this->hasMany(ProductInventory::class);
    }

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
        if ($this->brand && $this->brand->vendor && $this->brand->vendor->discount > $discount) {
            $vendor = $this->brand->vendor;
            
            // Check if vendor discount should apply based on first purchase rules
            $vendorDiscountApplies = !$enforce_first_purchase || !$has_orders || !$vendor->first_purchase_only;
            
            // Check if vendor minimum discount amount is met (if specified and vendorCartTotal is provided)
            $vendorMinimumMet = true;
            if ($vendor->minimum_discount_amount > 0 && $vendorCartTotal !== null) {
                $vendorMinimumMet = $vendorCartTotal >= $vendor->minimum_discount_amount;
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

        // Default opt-out for products with variations
        if (!empty($this->variation)) {
            return false;
        }

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
}
