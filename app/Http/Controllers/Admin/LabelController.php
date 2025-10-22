<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Label;
use Illuminate\Http\Request;
use Illuminate\Support\Str as Str;
use Closure;


class LabelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $labels = Label::query()
            ->when($request->q, function ($query, $q) {
                $query->where('name', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->paginate();

        $context = compact('labels');

        return view('labels.index', $context);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('labels.create');
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
                    $p = Label::where('slug', $slug)->first();
                    if ($p) {
                        $fail('El slug para este nombre ya existe');
                    }
                },
            ],
            'description' => 'nullable',
            'image' => 'nullable|image',
            'active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image_file')) {
            $validate['image'] = $request->image_file->store('/labels', 'public');
        }

        $slug =  Str::slug($request->name);
        $validate['slug'] = $slug;

        Label::create($validate);

        return to_route('labels.index')->with('success', 'La etiqueta se ha creado correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(Label $label)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Label $label)
    {
        $context = compact('label');
        return view('labels.edit', $context);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Label $label)
    {
        $validate = $request->validate([
            'name' => [
                'required',
                'max:255',
            ],
            'description' => 'nullable',
            'image' => 'nullable|image',
            'active' => 'nullable|boolean',
            'slug' => [
                'required',
                function (string $attribute, $value, Closure $fail) use ($label) {
                    $slug =  Str::slug($value);
                    $p = Label::whereNot('id', $label->id)->where('slug', $slug)->first();
                    if ($p) {
                        $fail('El slug para este nombre ya existe');
                    }
                },
            ]

        ]);


        $slug =  Str::slug($request->slug);
        $validate['slug'] = $slug;

        $label->update($validate);

        return to_route('labels.index')->with('success', 'La etiqueta actualizada');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Label $label)
    {
        if (!$label->products->count()) {
            $label->delete();
            return to_route('labels.index')->with('success', 'Etiqueta eliminada');
        }

        return back()->with('error', 'No es posible eliminar esta etiqueta por que tiene productos asociadas');
    }
}
