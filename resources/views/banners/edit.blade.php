@extends('layouts.admin')


@section('content')

<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Crear banner</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Información</h3>
            {{ Aire::open()->route('banners.update', $banner)->bind($banner)->enctype('multipart/form-data')}}
            <div class="grid grid-cols-6 gap-6">

                {{ Aire::input('url', "Link")->groupClass('col-span-6') }}
                {{Aire::select([1=>'Principal', 2=>'Lateral', 3=>'Intermedio'], 'type_id', 'Posición')->value(request()->get('type_id', 1))->groupClass('col-span-6')}}
                {{ Aire::file('file', "Banner")->groupClass('col-span-6') }}
               
                <div class="col-span-6 justify-between  items-center mt-5 space-x-2 flex">
                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Actualizar')->variant()->submit() }}
                        <a href="{{ route('banners.index') }}">Cancelar</a>
                    </p>
                </div>
            </div>
            {{ Aire::close() }}



        </div>

        <div class="flex justify-end">
            {{ Aire::open()->route('banners.destroy', $banner)}}
                <button class="inline-flex space-x-2 items-center bg-white hover:bg-red-700 border border-red-700 text-red-700 bg-white-700 hover:text-white focus:ring-0 focus:ring-red-300 font-medium rounded  px-2 py-1.5 text-center" onclick="return confirm('Esta seguro?');">Eliminar</button>
            {{ Aire::close() }}   
        </div>
    </div>

    <div>
          <img src="{{asset('storage/'.$banner->path)}}" class="w-full">
    </div>

   
</div>




@endsection
