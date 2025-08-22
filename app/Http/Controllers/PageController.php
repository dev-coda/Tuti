<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Label;
use App\Models\Product;
use App\Models\ZoneWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Transliterator;
use App\Models\City;
use App\Models\Contact;

class PageController extends Controller
{
    public function home()
    {
        $productsPre = Product::active()->with('images')->orderBy('created_at', 'desc')->limit(12);
        $products = $productsPre->paginate(4);
        $categories = Category::with('children')->whereNull('parent_id')->get();
        $banners = Banner::whereTypeId(1)->orderBy('id')->get();
        $lateral = Banner::whereTypeId(2)->orderBy('id')->get();
        // Removed hardcoded featured categories - now using API

        $context = compact('products', 'categories', 'banners', 'lateral');
        return view('pages.home', $context);
    }

    public function search(Request $request, $order = '1', $category_id = '0', $brand_id = '0')
    {
        $brands = Brand::whereActive(1)->orderBy('name')->get();
        $categories = Category::with('parent')->get();

        $transliterator = Transliterator::createFromRules(':: NFD; :: [:Mn:] Remove; :: NFC;');
        $q = $request->input('q');
        $q = $transliterator->transliterate($q);

        $params = compact('q', 'order', 'category_id', 'brand_id');

        // Split search query into individual words and filter out empty ones
        $searchWords = [];
        if ($q && trim($q) !== '') {
            $words = explode(' ', trim($q));
            foreach ($words as $word) {
                $word = trim($word);
                if ($word !== '' && strlen($word) >= 2) { // Only include words with 2+ characters
                    $searchWords[] = $word;
                }
            }
        }

        $productsQuery = Product::active()->with(['brand.vendor']);

        // Only apply search filter if we have valid search words
        if (!empty($searchWords)) {
            $productsQuery->where(function ($query) use ($searchWords) {
                $isFirst = true;
                foreach ($searchWords as $word) {
                    if ($isFirst) {
                        // First word uses 'where' to start the condition - supporting partial matches
                        $query->where(function ($subQuery) use ($word) {
                            $subQuery->whereRaw("unaccent(name) ~* ?", [$word])
                                ->orWhereRaw("unaccent(description) ~* ?", [$word])
                                ->orWhereRaw("unaccent(short_description) ~* ?", [$word])
                                ->orWhereRaw("unaccent(sku) ~* ?", [$word])
                                ->orWhereHas('brand', function ($brandQuery) use ($word) {
                                    $brandQuery->whereRaw("unaccent(name) ~* ?", [$word]);
                                })
                                ->orWhereHas('brand.vendor', function ($vendorQuery) use ($word) {
                                    $vendorQuery->whereRaw("unaccent(name) ~* ?", [$word]);
                                });
                        });
                        $isFirst = false;
                    } else {
                        // Subsequent words use 'orWhere' for OR logic - supporting partial matches
                        $query->orWhere(function ($subQuery) use ($word) {
                            $subQuery->whereRaw("unaccent(name) ~* ?", [$word])
                                ->orWhereRaw("unaccent(description) ~* ?", [$word])
                                ->orWhereRaw("unaccent(short_description) ~* ?", [$word])
                                ->orWhereRaw("unaccent(sku) ~* ?", [$word])
                                ->orWhereHas('brand', function ($brandQuery) use ($word) {
                                    $brandQuery->whereRaw("unaccent(name) ~* ?", [$word]);
                                })
                                ->orWhereHas('brand.vendor', function ($vendorQuery) use ($word) {
                                    $vendorQuery->whereRaw("unaccent(name) ~* ?", [$word]);
                                });
                        });
                    }
                }
            });
        } else if ($q && trim($q) !== '') {
            // If search query exists but no valid words found, return no results
            $productsQuery->whereRaw('1 = 0');
        }

        if ($category_id) {
            $productsQuery->whereHas('categories', function ($query) use ($category_id) {
                $query->where('category_id', $category_id);
            });
        }

        if ($brand_id) {
            $productsQuery->where('brand_id', $brand_id);
        }

        // Update brand filtering to match the same search logic
        if (!empty($searchWords)) {
            $productBrandIds = Product::active()
                ->with(['brand.vendor'])
                ->where(function ($query) use ($searchWords) {
                    $isFirst = true;
                    foreach ($searchWords as $word) {
                        if ($isFirst) {
                            $query->where(function ($subQuery) use ($word) {
                                $subQuery->whereRaw("unaccent(name) ~* ?", [$word])
                                    ->orWhereRaw("unaccent(description) ~* ?", [$word])
                                    ->orWhereRaw("unaccent(short_description) ~* ?", [$word])
                                    ->orWhereRaw("unaccent(sku) ~* ?", [$word])
                                    ->orWhereHas('brand', function ($brandQuery) use ($word) {
                                        $brandQuery->whereRaw("unaccent(name) ~* ?", [$word]);
                                    })
                                    ->orWhereHas('brand.vendor', function ($vendorQuery) use ($word) {
                                        $vendorQuery->whereRaw("unaccent(name) ~* ?", [$word]);
                                    });
                            });
                            $isFirst = false;
                        } else {
                            $query->orWhere(function ($subQuery) use ($word) {
                                $subQuery->whereRaw("unaccent(name) ~* ?", [$word])
                                    ->orWhereRaw("unaccent(description) ~* ?", [$word])
                                    ->orWhereRaw("unaccent(short_description) ~* ?", [$word])
                                    ->orWhereRaw("unaccent(sku) ~* ?", [$word])
                                    ->orWhereHas('brand', function ($brandQuery) use ($word) {
                                        $brandQuery->whereRaw("unaccent(name) ~* ?", [$word]);
                                    })
                                    ->orWhereHas('brand.vendor', function ($vendorQuery) use ($word) {
                                        $vendorQuery->whereRaw("unaccent(name) ~* ?", [$word]);
                                    });
                            });
                        }
                    }
                })
                ->pluck('brand_id')->toArray();
        } else {
            // If no search words, get all brand IDs for active products
            $productBrandIds = Product::active()->pluck('brand_id')->toArray();
        }

        $brands = $brands->filter(fn($brand) => in_array($brand->id, $productBrandIds));

        switch ($order) {
            case 1:
                $products = $productsQuery->orderBy('created_at', 'desc')->paginate();
                break;
            case 2:
                $products = $productsQuery->orderBy('price', 'asc')->paginate();
                break;
            case 3:
                $products = $productsQuery->orderBy('price', 'desc')->paginate();
                break;
            case 4:
                $products = $productsQuery->orderBy('name', 'asc')->paginate();
                break;
            case 5:
                $products = $productsQuery->orderBy('name', 'desc')->paginate();
                break;
            case 6:
                $products = $productsQuery->orderBy('sales_count', 'desc')->paginate();
                break;
            default:
                $products = $productsQuery->paginate();
                break;
        }

        $categoriesArray = [];
        foreach ($products as $item) {
            $values = $item->categories()->pluck('id')->toArray();
            $categoriesArray = array_merge($categoriesArray, $values);
        }

        $categories = $categories->filter(fn($category) => in_array($category->id, $categoriesArray));

        // Determine user's mapped bodega code
        $bodegaCode = $this->getUserBodegaCode();

        return view('pages.search', compact('products', 'brands', 'categories', 'params', 'bodegaCode'));
    }


    public function product($slug)
    {
        $product = Product::query()
            ->active()
            ->with(['related.images', 'items', 'variation', 'labels', 'inventories'])
            ->where('slug', $slug)->firstOrFail();
        $related = $product->related;

        if (!$related->count()) {
            $related = Product::active()->where('brand_id', $product->brand_id)->where('id', '!=', $product->id)->limit(4)->get();
        }

        $quantity = $product->step;

        $cart = session()->get('cart');
        if ($cart) {
            $product_id = $product->id;
            //check if product_id exists in cart  array_key_exists
            if (array_key_exists($product_id, $cart)) {
                $quantity = $cart[$product_id]['quantity'];
            }
        }

        $lateral = Banner::whereTypeId(2)->orderBy('id')->get();
        $intermedio = Banner::whereTypeId(3)->orderBy('id')->get();

        // Determine user's mapped bodega code
        $bodegaCode = $this->getUserBodegaCode();

        $context = compact('product', 'related', 'quantity', 'lateral', 'intermedio', 'bodegaCode');

        return view('pages.product', $context);
    }

    public function category($slug, $slug2 = '0', $order = '1', $category_id = '0', $brand_id = '0')
    {
        $brands = Brand::whereActive(1)->orderBy('name')->get();
        $banners = Banner::whereTypeId(1)->orderBy('id')->get();
        $categories = Category::with('parent')->get();
        $params = compact('slug', 'slug2', 'order', 'category_id', 'brand_id', 'banners');

        $category = $slug2
            ? Category::with('parent')->where('slug', $slug2)->firstOrFail()
            : Category::with('children')->where('slug', $slug)->firstOrFail();


        if ($slug2) {
            $productsQuery = Product::active()->whereHas('categories', function ($query) use ($category) {
                $query->where('category_id', $category->id);
            });
        } else {
            $ids = $category->children->pluck('id')->toArray();
            $ids[] = $category->id;

            $productsQuery = Product::active()->whereHas('categories', function ($query) use ($ids) {
                $query->whereIn('category_id', $ids);
            });
        }

        if ($category_id) {
            $productsQuery->whereHas('categories', function ($query) use ($category_id) {
                $query->where('category_id', $category_id);
            });
        }

        if ($brand_id) {
            $productsQuery->where('brand_id', $brand_id);
        }

        $productBrandIds = (clone $productsQuery)->pluck('brand_id')->unique()->toArray();

        $brands = $brands->filter(fn($brand) => in_array($brand->id, $productBrandIds));

        // Apply sorting - use category default if order is 0 or not specified
        if ($order == '0' || !$order) {
            // Use category's default sorting
            $sortOrder = $category->default_sort_order ?? 'most_recent';
            $productsQuery = $this->applyCategorySorting($productsQuery, $sortOrder);
        } else {
            // Use user-selected sorting
            switch ($order) {
                case 1:
                    $productsQuery = $productsQuery->orderBy('created_at', 'desc');
                    break;
                case 2:
                    $productsQuery = $productsQuery->orderBy('price', 'asc');
                    break;
                case 3:
                    $productsQuery = $productsQuery->orderBy('price', 'desc');
                    break;
                case 4:
                    $productsQuery = $productsQuery->orderBy('name', 'asc');
                    break;
                case 5:
                    $productsQuery = $productsQuery->orderBy('name', 'desc');
                    break;
                case 6:
                    $productsQuery = $productsQuery->orderBy('sales_count', 'desc');
                    break;
                default:
                    $productsQuery = $productsQuery->orderBy('created_at', 'desc');
                    break;
            }
        }

        // Handle highlighting and get final products
        $products = $this->getProductsWithHighlighting($category, $productsQuery, $order, $category_id, $brand_id);

        $categoriesArray = [];
        foreach ($products as $item) {
            $values = $item->categories()->pluck('id')->toArray();
            $categoriesArray = array_merge($categoriesArray, $values);
        }

        $categories = $categories->filter(fn($category) => in_array($category->id, $categoriesArray));

        // Determine user's mapped bodega code
        $bodegaCode = $this->getUserBodegaCode();

        return view('pages.category', compact('products', 'brands', 'categories', 'params', 'category', 'bodegaCode', 'banners'));
    }

    public function label($slug)
    {
        $label = Label::whereActive(1)->where('slug', $slug)->firstOrFail();
        $products = $label->products()->paginate();
        $context = compact('label', 'products');
        return view('pages.label', $context);
    }

    public function brands()
    {
        $brands = Brand::whereActive(1)->orderBy('name')->get();
        $context = compact('brands');
        return view('pages.brands', $context);
    }


    public function brand($slug)
    {
        $brand = Brand::whereActive(1)->where('slug', $slug)->firstOrFail();
        $products = $brand->products()->paginate();
        $context = compact('brand', 'products');
        return view('pages.brand', $context);
    }


    public function form()
    {
        $cities = City::orderBy('name')->pluck('name', 'id')->prepend('Selecciona tu ciudad', '');
        return view('pages.form', compact('cities'));
    }

    public function form_post(Request $request)
    {
        $validate = $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'city_id' => 'required|exists:cities,id',
            'nit' => 'required',
            'terms_accepted' => 'required|accepted',
        ]);

        // Remove terms_accepted from data to be saved (we just need to validate it was accepted)
        unset($validate['terms_accepted']);

        Contact::create($validate);

        return back()->with('success', 'Solicitud enviada correctamente. Nos pondremos en contacto contigo pronto.');
    }

    private function getUserBodegaCode(): ?string
    {
        if (!Auth::check()) {
            return null;
        }

        $user = Auth::user();

        // Try explicit user->zone first (commonly set for sellers)
        $zoneCode = $user->zone ?? null;

        // Fallback to first related zone's code, then zone name
        if (!$zoneCode) {
            $zoneCode = $user->zones()->orderBy('id')->value('code');
        }
        if (!$zoneCode) {
            $zoneCode = $user->zones()->orderBy('id')->value('zone');
        }

        if (!$zoneCode) {
            return null;
        }

        // Map via ZoneWarehouse (exact match, then case-insensitive)
        $bodega = ZoneWarehouse::where('zone_code', $zoneCode)->value('bodega_code');
        if (!$bodega) {
            $bodega = ZoneWarehouse::whereRaw('LOWER(zone_code) = ?', [mb_strtolower($zoneCode)])->value('bodega_code');
        }

        return $bodega ?: null;
    }

    /**
     * Apply category-specific sorting to a query
     */
    private function applyCategorySorting($query, $sortOrder)
    {
        switch ($sortOrder) {
            case 'most_recent':
                return $query->orderBy('created_at', 'desc');
            case 'price_asc':
                return $query->orderBy('price', 'asc');
            case 'price_desc':
                return $query->orderBy('price', 'desc');
            case 'name_asc':
                return $query->orderBy('name', 'asc');
            case 'name_desc':
                return $query->orderBy('name', 'desc');
            case 'best_selling':
                return $query->orderBy('sales_count', 'desc');
            default:
                return $query->orderBy('created_at', 'desc');
        }
    }

    /**
     * Get products with highlighting applied
     */
    private function getProductsWithHighlighting($category, $productsQuery, $order, $category_id, $brand_id)
    {
        // If highlighting is disabled for this category, return normal pagination
        if (!$category->enable_highlighting) {
            return $productsQuery->with('inventories')->paginate();
        }

        // Get all products first
        $allProducts = $productsQuery->with('inventories')->get();

        // Get highlighted products
        $highlightedProductIds = [];
        $highlightedByBrandIds = [];

        // Get specifically highlighted products (up to 4 positions)
        $specificHighlights = $category->highlightedProducts()
            ->with('product')
            ->orderBy('position')
            ->get();

        foreach ($specificHighlights as $highlight) {
            if ($highlight->product) {
                $highlightedProductIds[] = $highlight->product->id;
            }
        }

        // Get products from highlighted brands
        if (!empty($category->highlighted_brand_ids)) {
            $brandHighlights = $allProducts->whereIn('brand_id', $category->highlighted_brand_ids)
                ->whereNotIn('id', $highlightedProductIds); // Exclude already highlighted products

            foreach ($brandHighlights as $product) {
                $highlightedByBrandIds[] = $product->id;
            }
        }

        // Separate highlighted and regular products
        $highlighted = $allProducts->whereIn('id', array_merge($highlightedProductIds, $highlightedByBrandIds));
        $regular = $allProducts->whereNotIn('id', array_merge($highlightedProductIds, $highlightedByBrandIds));

        // Sort highlighted products by position (specific highlights first, then brand highlights)
        $sortedHighlighted = collect();

        // Add specifically highlighted products in position order
        foreach ($highlightedProductIds as $productId) {
            $product = $highlighted->where('id', $productId)->first();
            if ($product) {
                $sortedHighlighted->push($product);
            }
        }

        // Add brand highlighted products
        foreach ($highlightedByBrandIds as $productId) {
            $product = $highlighted->where('id', $productId)->first();
            if ($product) {
                $sortedHighlighted->push($product);
            }
        }

        // Merge highlighted and regular products
        $finalProducts = $sortedHighlighted->merge($regular);

        // Create a paginator manually
        $perPage = 15; // Default pagination size
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $itemsForCurrentPage = $finalProducts->slice($offset, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $itemsForCurrentPage,
            $finalProducts->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }
}
