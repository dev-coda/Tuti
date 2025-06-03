<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Label;
use App\Models\Product;
use Illuminate\Http\Request;
use Transliterator;
use function Laravel\Prompts\alert;

class PageController extends Controller
{
    public function home()
    {
        $productsPre = Product::active()->with('images')->orderBy('created_at', 'desc')->limit(12);
        $products = $productsPre->paginate(3);
        $categories = Category::with('children')->whereNull('parent_id')->get();
        $banners = Banner::whereTypeId(1)->orderBy('id')->get();
        $lateral = Banner::whereTypeId(2)->orderBy('id')->get();
        $featured = Category::whereId(3)->orWhere('id', 4)->orWhere('id', 17)->get();

        $context = compact('products', 'categories', 'banners', 'lateral', 'featured');
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

        $productsQuery = Product::active()->where(function ($query) use ($q) {
            $query->whereRaw("unaccent(name) ILIKE ?", ['%' . $q . '%'])
                ->orWhereRaw("unaccent(description) ILIKE ?", ['%' . $q . '%'])
                ->orWhereRaw("unaccent(lower(short_description)) ILIKE ?", ['%' . $q . '%'])
                ->orWhereRaw("unaccent(lower(sku)) ILIKE ?", ['%' . $q . '%']);
        });

        if ($category_id) {
            $productsQuery->whereHas('categories', function ($query) use ($category_id) {
                $query->where('category_id', $category_id);
            });
        }

        if ($brand_id) {
            $productsQuery->where('brand_id', $brand_id);
        }

        $productBrandIds = Product::active()
            ->where(function ($query) use ($q) {
                $query->whereRaw("unaccent(name) ILIKE ?", ['%' . $q . '%'])
                    ->orWhereRaw("unaccent(description) ILIKE ?", ['%' . $q . '%'])
                    ->orWhereRaw("unaccent(lower(short_description)) ILIKE ?", ['%' . $q . '%'])
                    ->orWhereRaw("unaccent(lower(sku)) ILIKE ?", ['%' . $q . '%']);
            })
            ->pluck('brand_id')->toArray();

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

        return view('pages.search', compact('products', 'brands', 'categories', 'params'));
    }


    public function product($slug)
    {
        $product = Product::query()
            ->active()
            ->with(['related.images', 'items', 'variation', 'labels'])
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
        $context = compact('product', 'related', 'quantity');

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
            default:
                $products = $productsQuery->paginate();
                break;
        }

        $categoriesArray = [];
        foreach ($products as $product) {
            $ids = $product->categories()->pluck('id')->toArray();
            $categoriesArray = array_merge($categoriesArray, $ids);
        }

        $categories = $categories->filter(fn($cat) => in_array($cat->id, $categoriesArray));

        $context = compact('category', 'products', 'categories', 'brands', 'params', 'banners');
        return view('pages.category', $context);
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
        return view('pages.form');
    }

    public function form_post(Request $request)
    {
        $validate = $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'business_name' => 'required',
            'city' => 'required',
            'nit' => 'required',
        ]);

        Contact::create($validate);

        return back()->with('success', 'Mensaje enviado correctamente');
    }
}
