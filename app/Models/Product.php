<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'short_description',
        'sku',
        'slug',
        'active',
        'price',
        'delivery_days',
        'discount',
        'quantity_min',
        'quantity_max',
        'step',
        'tax_id',
        'brand_id',
        'variation_id',
        'is_combined',
        'parent_id',
        'package_quantity',

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
        return $this->hasMany(ProductImage::class);
    }

    public function bonifications()
    {
        return $this->belongsToMany(Bonification::class);
    }

    public function getImageAttribute()
    {
        return $this->images->first()?->path;
    }

    public function getFinalPriceAttribute()
    {

        $discount = $this->discount;
        $discount_on = 'Producto';

        if ($this->brand) {
            if ($this->brand->discount > $discount) {
                $discount = $this->brand->discount;
                $discount_on = 'Marca';
            }
        }

        if ($this->brand->vendors) {
            if ($this->brand->vendors->discount > $discount) {
                $discount = $this->brand->vendors->discount;
                $discount_on = 'Vendor';
            }
        }
        

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
        $finalPerItemPrice = $finalPrice;
        $pricePreDiscount = $price + ($price * $this->taxValue() / 100);


        return [
            'old' => $pricePreDiscount * $packageQuantity,
            'price' => $finalPriceWithPackage,
            'totalDiscount' => ($price * $discount / 100) * $packageQuantity,
            'discount' => $discount,
            'discount_on' => $discount_on,
            'has_discount' => $has_discount,
            'originalPrice' => $price * $packageQuantity,
            'perItemPrice' => $this->step > 1 ? $finalPerItemPrice * $this->step : $finalPerItemPrice,
        ];
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
}
