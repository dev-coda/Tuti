@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Calendario de Entrega</h1>
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100">
            <form id='form' action="" class='flex flex-wrap items-center gap-4'>
                <input type="text" 
                       name="year" 
                       placeholder="Año" 
                       value="{{ request()->year }}"
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                
                {{ Aire::select(['' => 'Todos', 'A' => 'Ciclo A', 'B' => 'Ciclo B', 'C' => 'Ciclo C'], 'cycle')
                    ->id('cycle')
                    ->value(request()->cycle, '')
                    ->label('Ciclo') }}
            </form>
            <div class="flex flex-wrap gap-2 mt-4 sm:mt-0">
                <a href="{{ route('delivery-calendars.create') }}"
                    class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5">
                    ➕ Nueva entrada
                </a>
            </div>
        </div>
    </div>
</div>

<div class="flex flex-col">
    <div class="overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
            <div class="overflow-hidden shadow">
                <table class="min-w-full divide-y divide-gray-200 table-fixed">
                    <thead class="bg-gray-100">
                        <tr>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Año
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Mes
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Semana
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Fecha Inicio
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Fecha Fin
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Ciclo
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($calendars as $calendar)
                        <tr class="hover:bg-gray-100">
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                {{ $calendar->year }}
                            </td>
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                {{ $calendar->month }}
                            </td>
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                {{ $calendar->week_number }}
                            </td>
                            <td class="p-4 text-base font-medium text-gray-900 whitespace-nowrap">
                                {{ $calendar->start_date->format('d/m/Y') }}
                            </td>
                            <td class="p-4 text-base font-medium text-gray-900 whitespace-nowrap">
                                {{ $calendar->end_date->format('d/m/Y') }}
                            </td>
                            <td class="p-4 text-base font-medium text-gray-900 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    @if($calendar->cycle == 'A') bg-blue-100 text-blue-800
                                    @elseif($calendar->cycle == 'B') bg-green-100 text-green-800
                                    @else bg-purple-100 text-purple-800
                                    @endif">
                                    Ciclo {{ $calendar->cycle }}
                                </span>
                            </td>
                            <td class="p-4 space-x-2 whitespace-nowrap text-end">
                                <a href="{{ route('delivery-calendars.edit', $calendar) }}"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100">
                                    Editar
                                </a>
                                {{ Aire::open()->route('delivery-calendars.destroy', $calendar) }}
                                <button class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100" 
                                        onclick="return confirm('¿Está seguro?');">
                                    Eliminar
                                </button>
                                {{ Aire::close() }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{ $calendars->links() }}
@endsection

@section('scripts')
<script src="{{ asset('js/jquery.js') }}"></script>
<script>
    $(document).ready(function(){
        $('#cycle').on('change', function(){
            $('#form').submit();
        })
    })
</script>
@endsection

