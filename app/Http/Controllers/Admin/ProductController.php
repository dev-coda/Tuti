<?php

namespace App\Http\Controllers\Admin;
use App\Exports\ProductExport;
use App\Http\Controllers\Controller;

use App\Models\Bonification;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Label;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tax;
use App\Models\Variation;
use App\Models\VariationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Image;
use Illuminate\Support\Str as Str;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
{
    // Inicializamos la consulta de productos
    $products = Product::query()->with('tax');

    // Si hay un filtro de búsqueda
    //Filtro por nombre
    if ($request->has('filter')) {
        $products->where('name', 'like', '%' . $request->filter . '%');
    }

    // Filtro por SKU
    if ($request->has('sku') && $request->sku != '') {
        $products->where('sku', 'like', '%' . $request->sku . '%');
    }

    // Filtro por precio
    if ($request->has('min_price') && $request->min_price != '') {
        $products->where('price', '>=', $request->min_price);
    }
    if ($request->has('max_price') && $request->max_price != '') {
        $products->where('price', '<=', $request->max_price);
    }

    // Filtro por impuesto
    if ($request->has('tax_id') && $request->tax_id != '') {
        $products->where('tax_id', $request->tax_id);
    }

    // Filtro por estado
    if ($request->has('active') && $request->active != '') {
        $products->where('active', $request->active);
    }

    // Si hay parámetros de orden
    if ($request->has('sort_by') && $request->has('order')) {
        $products->orderBy($request->sort_by, $request->order);
    } else {
        // Si no hay orden, por defecto lo ordenamos por nombre
        $products->orderBy('name');
    }

    // Aplicamos paginación
    //Para que esto funcione hay que habilitar el unaccent en postgres: CREATE EXTENSION IF NOT EXISTS unaccent;
    $products = $products->when($request->q, function ($query, $q) {
        $q = Str::lower(Str::ascii($q)); // Normalizamos la entrada
        $query->whereRaw("unaccent(lower(name)) ILIKE ?", ['%' . $q . '%'])
        ->orWhereRaw("unaccent(lower(sku)) ILIKE ?", ['%' . $q . '%']);
    })
    ->paginate(10);

    // Pasamos los productos a la vista
    return view('products.index', compact('products'));
}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $brands = Brand::orderBy('name')->get()->pluck('name', 'id');
        $brands->prepend('Seleccione', null);

        $variations = Variation::orderBy('name')->get()->pluck('name', 'id');
        $variations->prepend('Seleccione', null);

        $taxes = Tax::orderBy('name')->get()->pluck('name', 'id');
        $labels = Label::orderBy('name')->get();


        $bonifications = Bonification::orderBy('name')->get()->pluck('name', 'id');
        $bonifications->prepend('Seleccione', null);

        $categories = Category::with('children')->whereNull('parent_id')->orderBy('name')->get();

        $context = compact('brands', 'taxes', 'labels', 'categories', 'variations', 'bonifications');
        return view('products.create', $context);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
     

        $validate = $request->validate([
            'name' => [
                'required',
                'max:255',
                function (string $attribute, $value, Closure $fail) {
                    $slug =  Str::slug($value);
                    $p = Product::where('slug', $slug)->first();
                    if ($p) {
                        $fail('El slug para este nombre ya existe');
                    }
                },
            ],
            'description' => 'nullable',
            'short_description' => 'nullable',
            'sku' => 'required',
            'active' => 'nullable|boolean',
            'price' => 'required',
            'delivery_days' => 'required',
            'discount' => 'numeric|min:0|max:100',
            'quantity_min' => 'required|numeric',
            'quantity_max' => 'required|numeric',
            'step' => 'required|numeric',
            'tax_id' => 'required',
            'brand_id' => 'required',
            'variation_id'=>'nullable',
            'is_combined' => 'nullable|boolean',
        ]);

        
  
        
        $categories = $request->categories;
        $labels = $request->labels;

        $slug =  Str::slug($request->name);
        $validate['slug'] = $slug;

        $product = Product::create($validate);
       
        $product->labels()->attach($labels);
        $product->categories()->attach($categories, );


        if($request->variation_id){
            $variations = VariationItem::whereVariationId($request->variation_id)->get();
            $product->items()->attach($variations, [
                'price'=> $validate['price'],
                'sku'=> $validate['sku'],
                'enabled'=>true
            ]);  
        }
       

        return to_route('products.edit', $product)->with('success', 'Producto creado');
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        $product->load([
            'brand', 
            'combinations',
            'related', 
            'items' => ['variation'],
            'variation',
            'images',
            'bonifications'
        ]); // eager loading

  

        $brands = Brand::orderBy('name')->get()->pluck('name', 'id');
        $brands->prepend('Seleccione', null);

        $variations = Variation::orderBy('name')->get()->pluck('name', 'id');
        $variations->prepend('Seleccione', null);

        $bonifications = Bonification::orderBy('name')->get()->pluck('name', 'id');
        $bonifications->prepend('Seleccione', null);

        $ids = $product->combinations()->get()->pluck('id')->toArray();
        $id = $product->id;
        $products = Product::query()
            ->with('variation', 'items')
            ->whereNot('id', $id)
            ->whereNotIn('id', $ids)
            ->whereActive(1)
            ->whereIsCombined(0)
            ->select(['name', 'id'])
            ->orderBy('name', 'asc')
            ->get()->pluck('name', 'id');
        $products->prepend('Seleccione', null);

        $categories = Category::with('children')->whereNull('parent_id')->orderBy('name')->get();
        $labels = Label::orderBy('name')->get();
        $taxes = Tax::orderBy('name')->get()->pluck('name', 'id');

        $context = compact('brands', 'taxes', 'product', 'categories', 'labels', 'variations', 'products', 'bonifications');


        return view('products.edit', $context);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
      
      
      

        $validate = $request->validate([
            'name' => 'required|max:255',
            'description' => 'nullable',
            'short_description' => 'nullable',
            'sku' => 'required',
            'active' => 'nullable|boolean',
            'price' => 'required',
            'delivery_days' => 'required',
            'discount' => 'numeric|min:0|max:100',
            'quantity_min' => 'required|numeric',
            'quantity_max' => 'required|numeric',
            'step' => 'required|numeric',
            'tax_id' => 'required',
            'brand_id' => 'required',
            'variation_id'=>'nullable',
            'slug' => [
                'required',
                function (string $attribute, $value, Closure $fail) use ($product) {
                    $slug =  Str::slug($value);
                    $p = Product::whereNot('id', $product->id)->where('slug', $slug)->first();
                    if ($p) {
                        $fail('El slug ya existe');
                    }
                },
            ]
        ]);

        $validate['slug'] =  Str::slug($request->slug);

        $product->labels()->sync($request->labels);
        $product->categories()->sync($request->categories);

        $product->items()->sync($request->variations);

        $product->update($validate);
        # return back()->with('success', 'Producto actualizado');
        if($request->bonification_id){
            $bonification = Bonification::find($request->bonification_id);
            $product->bonifications()->detach();
            $bonification->products()->attach($product->id);
        }else{
            $product->bonifications()->detach();
        }
      

        return to_route('products.index')->with('success', "Producto actualizado");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->load('orders');

        if(!$product->orders->isEmpty()){
            $product->update(['active'=>0]);
            return back()->with('error', 'No se puede eliminar el producto, tiene pedidos asociados, producto desactivado');
        }

        $product->delete();

        return to_route('products.index')->with('success', 'Producto eliminado');

        //TODO validar que no tenga pedidos
    }


    public function images(Request $request, Product $product){

        $validate = $request->validate([
            'image' => 'required|image|max:4096',
        ]);

        $path = $validate['image']->store('products', 'public');

        $product->images()->create([    
            'path' => $path
        ]);

        return back()->with('success', 'Imagen cargada');

    }

    public function images_delete(Request $request, Product $product, ProductImage $image){

        $image->delete();

        return back()->with('success', 'Imagen eliminada');

    }

    public function export()
    {
        return Excel::download(new ProductExport, 'productos.xlsx');
    }
}
