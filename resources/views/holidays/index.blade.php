@extends('layouts.admin')

@section('content')
<!-- Import Results Modal -->
@if(session('import_stats'))
<div id="importModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">📊 Resultados de la Importación</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <span class="text-2xl">&times;</span>
            </button>
        </div>

        <div class="space-y-4">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">{{ session('import_stats')['imported'] }}</div>
                    <div class="text-sm text-green-800">Importados</div>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-600">{{ session('import_stats')['duplicates'] }}</div>
                    <div class="text-sm text-yellow-800">Duplicados</div>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-red-600">{{ session('import_stats')['errors'] }}</div>
                    <div class="text-sm text-red-800">Errores</div>
                </div>
            </div>

            <!-- Error Details -->
            @if(session('import_stats')['error_details'] && count(session('import_stats')['error_details']) > 0)
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h4 class="font-medium text-red-800 mb-2">⚠️ Errores encontrados:</h4>
                <ul class="text-sm text-red-700 space-y-1 max-h-40 overflow-y-auto">
                    @foreach(session('import_stats')['error_details'] as $error)
                    <li>• {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="flex justify-end space-x-2">
                <button onclick="closeModal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
@endif



<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Festivos</h1>
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100 ">
            <form id='form' action="" class='flex flex-wrap items-center gap-4'>
                {{ Aire::select([0=>'Todos', 1=>'Festivo', 2=>'Sábado'], 'type_id')->id("type_id")->value(request()->type_id, 0 ) }}

                <label class="flex items-center space-x-2 cursor-pointer">
                    <!-- Hidden input to ensure a value is always sent -->
                    <input type="hidden" name="show_past" value="0">
                    <input type="checkbox"
                           name="show_past"
                           value="1"
                           {{ (request()->has('show_past') ? request('show_past') : true) ? 'checked' : '' }}
                           onchange="this.form.submit()"
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <span class="text-sm text-gray-700">Mostrar festivos pasados</span>
                </label>
            </form>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('holidays.create') }}"
                    class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5 ">
                    ➕ Nueva fecha
                </a>
                <a href="{{ route('holidays.import') }}"
                    class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:ring-purple-300 font-bold rounded-lg text-sm px-5 py-2.5 flex items-center">
                    📤 Importar CSV
                </a>
                <a href="{{ route('holidays.export') }}"
                    class="text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 font-bold rounded-lg text-sm px-5 py-2.5 flex items-center">
                    📥 Exportar CSV
                </a>
                <a href="{{ route('holidays.debug') }}"
                    class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-bold rounded-lg text-sm px-5 py-2.5 flex items-center">
                    🔍 Debug Datos
                </a>
            </div>
        </div>
    </div>
</div>
<div class="flex flex-col">
    <div class="overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
            <div class="overflow-hidden shadow">
                <table class="min-w-full divide-y divide-gray-200 table-fixed ">
                    <thead class="bg-gray-100">
                        <tr>
                           
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Nombre
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Fecha
                            </th>
                          
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase "></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 ">
                        @foreach ($holidays as $holiday)
                        <tr class="hover:bg-gray-100">
                            
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                <a class="flex flex-col text-gray-900  hover:text-blue-500" href="{{ route('holidays.edit', $holiday) }}">
                                    <span class="text-base font-semibold ">
                                        {{ $holiday->type }}
                                    </span>
                                    <small class="text-xs text-gray-500">
                                        {{ $holiday->day_type === 'laboral' ? 'Laboral' : 'Festivo' }}
                                    </small>
                                </a>
                            </td>
                            <td class="p-4 text-base font-medium text-gray-900 whitespace-nowra">
                                <div class="flex flex-col">
                                    <span>{{ $holiday->date->toDateString() }}</span>
                                    <small class='text-gray-500'> {{ $holiday->day }}</small>
                                </div>
                            </td>
                          

                            <td class="p-4 space-x-2 whitespace-nowrap text-end">

                                {{ Aire::open()->route('holidays.destroy', $holiday)}}
                                <button class="inline-flex space-x-2 items-center bg-white hover:bg-red-700 border border-red-700 text-red-700 bg-white-700 hover:text-white focus:ring-0 focus:ring-red-300 font-medium rounded text-xs px-2 py-1.5 text-center" onclick="return confirm('Esta seguro?');">Eliminar</button>
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


{{ $holidays->links() }} 











@endsection



@section('scripts')

<script src="{{ asset('js/jquery.js') }}" ></script>
<script>

    $(document).ready(function(){
        $('#type_id').on('change', function(){
            $('#form').submit();
        })

        // Auto-show import modal if there are results
        @if(session('import_stats'))
            $('#importModal').show();
        @endif
    })

    function closeModal() {
        $('#importModal').hide();
    }

    // Close modal when clicking outside
    $(document).on('click', '#importModal', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Close modal on ESC key
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && $('#importModal').is(':visible')) {
            closeModal();
        }
    });

</script>

@endsection
