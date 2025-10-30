<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Closure;

class ContentPageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $pages = ContentPage::query()
            ->when($request->q, function ($query, $q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            })
            ->orderBy('title')
            ->paginate();

        $context = compact('pages');

        return view('content-pages.index', $context);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('content-pages.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validate = $request->validate([
            'title' => [
                'required',
                'max:255',
            ],
            'slug' => [
                'required',
                'max:255',
                'unique:content_pages,slug',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],
            'content' => 'nullable|string',
            'enabled' => 'nullable|boolean',
        ], [
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones.',
            'slug.unique' => 'Ya existe una página con este slug.',
        ]);

        // Ensure enabled is set
        $validate['enabled'] = $request->has('enabled') ? true : false;

        ContentPage::create($validate);

        return to_route('content-pages.index')->with('success', 'La página de contenido se ha creado correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(ContentPage $contentPage)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ContentPage $contentPage)
    {
        $context = compact('contentPage');
        return view('content-pages.edit', $context);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ContentPage $contentPage)
    {
        $validate = $request->validate([
            'title' => [
                'required',
                'max:255',
            ],
            'slug' => [
                'required',
                'max:255',
                'unique:content_pages,slug,' . $contentPage->id,
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],
            'content' => 'nullable|string',
            'enabled' => 'nullable|boolean',
        ], [
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones.',
            'slug.unique' => 'Ya existe una página con este slug.',
        ]);

        // Ensure enabled is set
        $validate['enabled'] = $request->has('enabled') ? true : false;

        $contentPage->update($validate);

        return to_route('content-pages.edit', $contentPage)->with('success', 'La página de contenido se ha actualizado correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ContentPage $contentPage)
    {
        $contentPage->delete();

        return to_route('content-pages.index')->with('success', 'La página de contenido se ha eliminado correctamente');
    }
}
