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
        $products = Product::active()->with('images')->orderBy('created_at', 'desc')->paginate(12);
        $categories = Category::with('children')->whereNull('parent_id')->get();
        $banners = Banner::whereTypeId(1)->orderBy('id')->get();
        $lateral = Banner::whereTypeId(2)->orderBy('id')->get();

        $context = compact('products', 'categories', 'banners', 'lateral');
        return view('pages.home', $context);
    }

    public function search(Request $request, $order = '1', $category_id = '0', $brand_id = '0')
    {

        $brands = Brand::whereActive(1)->orderBy('name')->get();
        $categories = Category::with('parent')->get();
        $productBrandIds = null;

        $transliterator = Transliterator::createFromRules(':: NFD; :: [:Mn:] Remove; :: NFC;');
        $q = $request->input('q');
        $q = $transliterator->transliterate($q);

        $params = compact('q', 'order', 'category_id', 'brand_id');

        $products = Product::active()->where(function ($query) use ($q) {
            $query->whereRaw("unaccent(name) ILIKE ?", ['%' . $q . '%'])
                ->orWhereRaw("unaccent(description) ILIKE ?", ['%' . $q . '%'])
                ->orWhereRaw("unaccent(lower(short_description)) ILIKE ?", ['%' . $q . '%'])
                ->orWhereRaw("unaccent(lower(sku)) ILIKE ?", ['%' . $q . '%']);
        });

        $productBrandIds = $products->pluck('brand_id')->toArray();

        $brands = $brands->filter(function ($brand) use ($productBrandIds) {
            if (in_array($brand->id, $productBrandIds)) {
                return $brand;
            } else {
                return false;
            };
        });

        $categoriesArray = [];

        $productCategories = $products->each(function ($item) use (&$categoriesArray) {
            $values = join(',', $item->categories()->pluck('id')->toArray());
            array_push($categoriesArray, $values);
        });


        $categories = $categories->filter(function ($category) use ($categoriesArray) {
            if (in_array($category->id, $categoriesArray)) {
                return $category;
            } else {
                return false;
            };
        });


        if ($category_id) {
            $products = $products->whereHas('categories', function ($query) use ($category_id) {
                $query->where('category_id', $category_id);
            });
        }

        if ($brand_id) {
            $products = $products->where('brand_id', $brand_id);
        }

        switch ($order) {
            case 1:
                $products = $products->orderBy('created_at', 'desc')->paginate();
                break;
            case 2:
                $products = $products->orderBy('price', 'asc')->paginate();
                break;
            case 3:
                $products = $products->orderBy('price', 'desc')->paginate();
                break;
            case 4:
                $products = $products->orderBy('name', 'asc')->paginate();
                break;
            case 5:
                $products = $products->orderBy('name', 'desc')->paginate();
                break;
            default:
                $products = $products->paginate();
                break;
        }

        $context = compact('products', 'brands', 'categories', 'params');
        return view('pages.search', $context);
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

        if ($slug2) {

            $category = Category::with('parent')->where('slug', $slug2)->firstOrFail();
            $products = $category->products();

        } else {

            $category = Category::where('slug', $slug)->firstOrFail();
            $products = $category->products();

            //seleccion el id de las categorias padres 
            $ids = $category->children->pluck('id')->toArray();

            $ids = array_merge($ids, [$category->id]);

            $products = Product::active()->whereHas('categories', function ($query) use ($ids) {
                $query->whereIn('category_id', $ids);
            });
        }

        $productBrandIds = $products->pluck('brand_id')->toArray();

        $brands = $brands->filter(function ($brand) use ($productBrandIds) {
            if (in_array($brand->id, $productBrandIds)) {
                return $brand;
            } else {
                return false;
            };
        });

        $categoriesArray = [];

        $productCategories = $products->each(function ($item) use (&$categoriesArray) {
            $values = join(',', $item->categories()->pluck('id')->toArray());
            array_push($categoriesArray, $values);
        });


        $categories = $categories->filter(function ($category) use ($categoriesArray) {
            if (in_array($category->id, $categoriesArray)) {
                return $category;
            } else {
                return false;
            };
        });

        if ($category_id) {
            $products = $products->whereHas('categories', function ($query) use ($category_id) {
                $query->where('category_id', $category_id);
            });
        }

        if ($brand_id) {
            $products = $products->where('brand_id', $brand_id);
        }

        switch ($order) {
            case 1:
                $products = $products->orderBy('created_at', 'desc')->paginate();
                break;
            case 2:
                $products = $products->orderBy('price', 'asc')->paginate();
                break;
            case 3:
                $products = $products->orderBy('price', 'desc')->paginate();
                break;
            case 4:
                $products = $products->orderBy('name', 'asc')->paginate();
                break;
            case 5:
                $products = $products->orderBy('name', 'desc')->paginate();
                break;
            default:
                $products = $products->paginate();
                break;
        }

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
        ]);

        Contact::create($validate);

        return back()->with('success', 'Mensaje enviado correctamente');
    }
}
