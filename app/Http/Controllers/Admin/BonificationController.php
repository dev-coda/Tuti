<?php


namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;


use App\Models\Bonification;
use App\Models\Product;
use Illuminate\Http\Request;

class BonificationController extends Controller
{
    public function index(Request $request){

        $bonifications = Bonification::query()
        ->with('product')
        ->withCount('products')
        ->when($request->q, function($query, $q){
            $query->where('name', 'like', "%{$q}%");
        })
        ->orderBy('name')
        ->paginate();
       
        $context = compact('bonifications'); 

        return view('bonifications.index', $context);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {   
        $products = Product::pluck('name', 'id');
        $context = compact('products');
        return view('bonifications.create', $context);
    }

    public function store(Request $request)
    {
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'buy' => 'required|integer',
            'get' => 'required|integer|lte:buy',
            'max'=>   'required|integer|min:1',
            'product_id'=>'required|exists:products,id',
            'allow_discounts' => 'boolean'
        ]);

        // Handle checkbox: if not present, set to false
        $validate['allow_discounts'] = $request->has('allow_discounts') ? (bool)$request->input('allow_discounts') : false;

        $bonification = Bonification::create($validate);

        return to_route('bonifications.edit', $bonification)->with('success', 'Bonificación creada, agregue los productos');
    }


     /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bonification $bonification)
    {
      
        $ids = $bonification->products()->pluck('product_id')->toArray();
       
        $products = Product::query()
            ->doesntHave('bonifications')
            ->whereNotIn('id', $ids)
            ->select(['name', 'id'])
            ->orderBy('name', 'asc')
            ->get();
    
        $products_free = Product::pluck('name', 'id');
        $context = compact('bonification', 'products', 'products_free');
        return view('bonifications.edit', $context);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Bonification $bonification )
    {
        
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'buy' => 'required|integer',
            'get' => 'required|integer|lte:buy',
            'max'=>   'required|integer|min:1',
            'product_id'=>'required|exists:products,id',
            'allow_discounts' => 'boolean'
        ]);

        // Handle checkbox: if not present, set to false
        $validate['allow_discounts'] = $request->has('allow_discounts') ? (bool)$request->input('allow_discounts') : false;

        $bonification->update($validate);

        return to_route('bonifications.index')->with('success', 'Bonificación actualizada');
    }

     /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bonification $bonification)
    {
       

        $bonification->products()->delete();
        $bonification->delete();
        return to_route('bonifications.index')->with('success', 'La bonificacion se ha eliminado correctamente');

    }


}
