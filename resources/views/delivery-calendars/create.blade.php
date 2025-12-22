@extends('layouts.admin')

@section('content')
{{ Aire::open()->route('delivery-calendars.store') }}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Nueva Entrada de Calendario</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Información</h3>

            <div class="grid grid-cols-2 gap-6">
                {{ Aire::input('year', 'Año')->groupClass('col-span-6 sm:col-span-3') }}
                {{ Aire::input('month', 'Mes')->groupClass('col-span-6 sm:col-span-3') }}
                {{ Aire::number('week_number', 'Número de Semana')->groupClass('col-span-6 sm:col-span-3') }}
                
                <div class="col-span-6 sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                    <div class="relative max-w-sm">
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z"/>
                            </svg>
                        </div>
                        <input datepicker 
                               value="{{ old('start_date') }}" 
                               name="start_date" 
                               datepicker-format="yyyy-mm-dd" 
                               type="text" 
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5" 
                               placeholder="Seleccione">
                    </div>
                </div>

                <div class="col-span-6 sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                    <div class="relative max-w-sm">
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z"/>
                            </svg>
                        </div>
                        <input datepicker 
                               value="{{ old('end_date') }}" 
                               name="end_date" 
                               datepicker-format="yyyy-mm-dd" 
                               type="text" 
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5" 
                               placeholder="Seleccione">
                    </div>
                </div>

                {{ Aire::select(['A' => 'Ciclo A', 'B' => 'Ciclo B', 'C' => 'Ciclo C'], 'cycle', 'Ciclo')->groupClass('col-span-6 sm:col-span-3') }}

                <div class="col-span-6 justify-between items-center mt-5 space-x-2 flex">
                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Crear')->variant()->submit() }}
                        <a href="{{ route('delivery-calendars.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
{{ Aire::close() }}
@endsection

