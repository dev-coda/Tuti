<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;

use App\Jobs\ProcessImage;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str as Str;
use Closure;
use Faker\Core\File;
use Illuminate\Support\Facades\Storage;
use Image;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $categories = Category::query()
        ->with('parent')
        ->when($request->q, function($query, $q){
            $query->where('name', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%");
        })
        ->orderBy('name')
        ->paginate();
       
        $context = compact('categories'); 
        
        return view('categories.index', $context);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        $categories = Category::active()->whereNull('parent_id')->orderBy('name')->get()->pluck('name', 'id');
        $categories->prepend('Seleccione', null);

        $context = compact('categories');

        return view('categories.create', $context);
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
                function (string $attribute, $value, Closure $fail){
                    $slug =  Str::slug($value);
                    $p = Category::where('slug', $slug)->first();
                    if($p){
                        $fail('El slug para este nombre ya existe');
                    }
                },
            ],
            'parent_id' => 'nullable',
            'description' => 'nullable',
            'image' => 'nullable|image',
            'active' => 'nullable|boolean',
        ]);

        if($request->hasFile('image_file')){
            $validate['image'] = $request->image_file->store('/categories', 'do');
        }

        $slug =  Str::slug($request->name);
        $validate['slug'] = $slug;

        $category = Category::create($validate);

        return to_route('categories.index')->with('success', 'La categoría se ha creado correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {

       # dd(ProcessImage::dispatch('hola', 'categories'));
     
        $categories = Category::active()->whereNot('id', $category->id)->whereNull('parent_id')->orderBy('name')->get()->pluck('name', 'id');
        $categories->prepend('Seleccione', null);
        $context = compact('category', 'categories');
        return view('categories.edit', $context);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        
        // $d =  Storage::disk('s3')->put("tuti", $request->image_file);
        // dd($d);

        $validate = $request->validate([
            'name' => [
                'required', 
                'max:255',
                
            ],
            'description' => 'nullable',
            'parent_id' => 'nullable',
            'image_file' => 'nullable|image|max:2000',
            'active' => 'nullable|boolean',
            'slug' => [
                'required',
                function (string $attribute, $value, Closure $fail) use($category){
                    $slug =  Str::slug($value);
                    $p = Category::whereNot('id', $category->id)->where('slug', $slug)->first();
                    if($p){
                        $fail('El slug para este nombre ya existe');
                    }
                },
            ]
        ]);

       
        // $name = asset_name('categories'); 
        // $n = ProcessImage::dispatch($name, $request->image_file);
        
        // $image = $request->image_file;
        
        // $folder = 'categories';
        // $imgFile = Image::make($image->getRealPath());

     
        // Storage::disk('do')->put("{$name}.jpg", $imgFile->stream());
        
    
        // $imgFile->resize(500, 500, function ($constraint) {$constraint->aspectRatio();});
        // Storage::disk('do')->put("{$name}-500x500.jpg", $imgFile->encode('jpg', 75)->stream());


        // dd(file_get_contents($imgFile->));
        // Storage::disk('do')->put("image,hog", file_get_contents($imgFile));

              
        if($request->hasFile('image_file')){
            $validate['image'] = $request->image_file->store('/categories', 'do'); 
           # ProcessImage::dispatch($category, 'categories');

        }

        $slug =  Str::slug($request->name);
        $validate['slug'] = $slug;

        $category->update($validate);

        return to_route('categories.index')->with('success', 'La categoría se ha actualizado correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
       
        if($category->children()->count()){
            return back()->with('error', 'No se puede eliminar una categoría que tiene subcategorías');
        }

        if($category->products()->count()){
            return back()->with('error', 'No se puede eliminar una categoría que tiene productos asociados');
        }
        
        $category->delete();
        return to_route('categories.index')->with('success', 'La categoría se ha eliminado correctamente');

    }
}
