<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $banners = Banner::orderBy('id')->get();
        $context = compact('banners');

        return view('banners.index', $context);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('banners.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validate = [
            'file' => 'required|image',
        ];

        if ($request->filled('url')) {
            $validate['url'] = 'url';
        }

        $request->validate($validate);

        $path = $request->file('file')->store('banners', 'public');

        Banner::create([
            'path' => $path,
            'url' => $request->url,
            'type_id' => $request->type_id,
        ]);

        return to_route('banners.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Banner $banner)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Banner $banner)
    {
        $context = compact('banner');

        return view('banners.edit', $context);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Banner $banner)
    {
        //if url in filled validate the url

        if ($request->filled('url')) {
            $request->validate([
                'url' => 'url',
            ]);
        }

        $data = $request->only('url', 'type_id');

        if ($request->hasFile('file')) {
            $request->validate([
                'file' => 'required|image',
            ]);
            $data['path'] = $request->file('file')->store('banners', 'public');
        }


        $banner->update($data);

        return to_route('banners.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Banner $banner)
    {
        $banner->delete();

        return to_route('banners.index')->with('success', 'Banner eliminado');
    }
}
