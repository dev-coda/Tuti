<?php

namespace App\Services;

use App\Models\Product;
use App\Models\UpsellZone;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class UpsellService
{
    /**
     * Get products for a specific upsell zone
     *
     * @param UpsellZone $zone
     * @param Product|null $currentProduct
     * @param User|null $user
     * @return Collection
     */
    public function getProductsForZone(UpsellZone $zone, ?Product $currentProduct = null, ?User $user = null): Collection
    {
        $products = collect();
        $maxProducts = $zone->max_products;

        // Get active rules for this zone, ordered by priority
        $rules = $zone->activeRules()->orderByPivot('priority', 'desc')->get();

        foreach ($rules as $rule) {
            if ($products->count() >= $maxProducts) {
                break;
            }

            $zoneRuleConfig = $rule->pivot->config ?? [];
            $ruleConfig = array_merge($rule->config ?? [], $zoneRuleConfig);

            $ruleProducts = $this->evaluateRule($rule, $currentProduct, $user, $ruleConfig);
            
            // Merge products, avoiding duplicates
            foreach ($ruleProducts as $product) {
                if ($products->count() >= $maxProducts) {
                    break 2;
                }
                
                if (!$products->contains('id', $product->id)) {
                    // Exclude current product if provided
                    if ($currentProduct && $product->id === $currentProduct->id) {
                        continue;
                    }
                    
                    // Only include active products
                    if ($product->active) {
                        $products->push($product);
                    }
                }
            }
        }

        // If we still don't have enough products and there are manually assigned products
        if ($products->count() < $maxProducts) {
            $manualProducts = $zone->products()
                ->where('active', true)
                ->orderByPivot('position')
                ->get();

            foreach ($manualProducts as $product) {
                if ($products->count() >= $maxProducts) {
                    break;
                }

                if ($currentProduct && $product->id === $currentProduct->id) {
                    continue;
                }

                if (!$products->contains('id', $product->id)) {
                    $products->push($product);
                }
            }
        }

        return $products->take($maxProducts);
    }

    /**
     * Evaluate a specific rule and return products
     *
     * @param \App\Models\UpsellRule $rule
     * @param Product|null $currentProduct
     * @param User|null $user
     * @param array $config
     * @return Collection
     */
    protected function evaluateRule($rule, ?Product $currentProduct, ?User $user, array $config): Collection
    {
        $limit = $config['limit'] ?? 10;

        switch ($rule->type) {
            case 'recent_orders':
                return $this->getRecentOrderProducts($user, $currentProduct, $limit, $config);
            
            case 'favorite_products':
                return $this->getFavoriteProducts($user, $currentProduct, $limit);
            
            case 'same_category':
                return $this->getSameCategoryProducts($currentProduct, $limit);
            
            case 'same_brand':
                return $this->getSameBrandProducts($currentProduct, $limit);
            
            case 'best_selling':
                return $this->getBestSellingProducts($currentProduct, $limit);
            
            case 'related_products':
                return $this->getRelatedProducts($currentProduct, $limit);
            
            case 'manual':
                // Manual products are handled separately
                return collect();
            
            default:
                return collect();
        }
    }

    /**
     * Get products from user's recent orders
     */
    protected function getRecentOrderProducts(?User $user, ?Product $currentProduct, int $limit, array $config): Collection
    {
        if (!$user) {
            return collect();
        }

        $days = $config['days'] ?? 90;
        $dateFrom = now()->subDays($days);

        $orderProductIds = $user->orders()
            ->where('created_at', '>=', $dateFrom)
            ->with('products.product')
            ->get()
            ->pluck('products')
            ->flatten()
            ->pluck('product_id')
            ->unique()
            ->toArray();

        if (empty($orderProductIds)) {
            return collect();
        }

        $query = Product::active()
            ->whereIn('id', $orderProductIds)
            ->with('images');

        if ($currentProduct) {
            $query->where('id', '!=', $currentProduct->id);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Get user's favorite products
     */
    protected function getFavoriteProducts(?User $user, ?Product $currentProduct, int $limit): Collection
    {
        if (!$user) {
            return collect();
        }

        $query = $user->favoriteProducts()
            ->where('active', true)
            ->with('images');

        if ($currentProduct) {
            $query->where('products.id', '!=', $currentProduct->id);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Get products from the same category
     */
    protected function getSameCategoryProducts(?Product $currentProduct, int $limit): Collection
    {
        if (!$currentProduct) {
            return collect();
        }

        $categoryIds = $currentProduct->categories->pluck('id')->toArray();

        if (empty($categoryIds)) {
            return collect();
        }

        return Product::active()
            ->where('id', '!=', $currentProduct->id)
            ->whereHas('categories', function ($query) use ($categoryIds) {
                $query->whereIn('category_id', $categoryIds);
            })
            ->with('images')
            ->limit($limit)
            ->get();
    }

    /**
     * Get products from the same brand
     */
    protected function getSameBrandProducts(?Product $currentProduct, int $limit): Collection
    {
        if (!$currentProduct || !$currentProduct->brand_id) {
            return collect();
        }

        return Product::active()
            ->where('brand_id', $currentProduct->brand_id)
            ->where('id', '!=', $currentProduct->id)
            ->with('images')
            ->limit($limit)
            ->get();
    }

    /**
     * Get best selling products
     */
    protected function getBestSellingProducts(?Product $currentProduct, int $limit): Collection
    {
        $query = Product::active()
            ->where('sales_count', '>', 0)
            ->orderBy('sales_count', 'desc')
            ->with('images');

        if ($currentProduct) {
            $query->where('id', '!=', $currentProduct->id);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Get related products from product_related table
     */
    protected function getRelatedProducts(?Product $currentProduct, int $limit): Collection
    {
        if (!$currentProduct) {
            return collect();
        }

        return $currentProduct->related()
            ->where('active', true)
            ->with('images')
            ->limit($limit)
            ->get();
    }

    /**
     * Get products for a zone by slug (convenience method)
     */
    public function getProductsForZoneBySlug(string $slug, ?Product $currentProduct = null, ?User $user = null): Collection
    {
        $zone = UpsellZone::findBySlug($slug);
        
        if (!$zone || !$zone->active) {
            return collect();
        }

        return $this->getProductsForZone($zone, $currentProduct, $user);
    }
}
