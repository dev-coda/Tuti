@extends('layouts.admin')

@section('content')
{{ Aire::open()->route('route-cycles.store') }}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Nueva Ruta</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Información</h3>

            <div class="grid grid-cols-2 gap-6">
                {{ Aire::input('route', 'Ruta')->groupClass('col-span-6 sm:col-span-3') }}
                {{ Aire::select(['A' => 'Ciclo A', 'B' => 'Ciclo B', 'C' => 'Ciclo C'], 'cycle', 'Ciclo')->groupClass('col-span-6 sm:col-span-3') }}

                <div class="col-span-6 justify-between items-center mt-5 space-x-2 flex">
                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Crear')->variant()->submit() }}
                        <a href="{{ route('route-cycles.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
{{ Aire::close() }}
@endsection

