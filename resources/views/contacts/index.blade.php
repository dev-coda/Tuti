@extends('layouts.admin')


@section('content')



<div class="p-4 bg-white block border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4 flex justify-between items-center">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Interesados</h1>
        </div>
        
        <!-- Date Filter Form -->
        <form method="GET" action="{{ route('contacts.index') }}" class="mb-4" id="filterForm">
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                    <input 
                        type="date" 
                        name="date_from" 
                        id="date_from"
                        value="{{ request('date_from') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
                
                <div class="flex-1 min-w-[200px]">
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                    <input 
                        type="date" 
                        name="date_to" 
                        id="date_to"
                        value="{{ request('date_to') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <div class="flex-1 min-w-[160px]">
                    <label for="status_filter" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select name="status" id="status_filter"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        @foreach(\App\Models\Contact::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="flex gap-2">
                    <button 
                        type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300"
                    >
                        Filtrar
                    </button>
                    
                    <a 
                        href="{{ route('contacts.index') }}"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-200"
                    >
                        Limpiar
                    </a>
                    
                    <a 
                        href="{{ route('admin.export.contacts', ['date_from' => request('date_from'), 'date_to' => request('date_to')]) }}"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300 flex items-center gap-2"
                        title="Descargar CSV"
                    >
                        @svg('heroicon-o-arrow-down-on-square', 'w-5 h-5')
                        Exportar CSV
                    </a>
                </div>
            </div>
        </form>

        @if(request('date_from') || request('date_to'))
            <div class="text-sm text-gray-600 mb-2">
                Mostrando registros 
                @if(request('date_from'))
                    desde <strong>{{ \Carbon\Carbon::parse(request('date_from'))->format('d/m/Y') }}</strong>
                @endif
                @if(request('date_to'))
                    hasta <strong>{{ \Carbon\Carbon::parse(request('date_to'))->format('d/m/Y') }}</strong>
                @endif
            </div>
        @endif
    </div>
</div>
<div class="flex flex-col">
    <div class="overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
            <div class="overflow-hidden shadow">
                <table class="min-w-full divide-y divide-gray-200 table-fixed ">
                    <thead class="bg-gray-100">
                        <tr>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase w-20">
                                Acciones
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Identificación
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Nombre del cliente
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Zona
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Transmitir
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Estado
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 ">
                        @foreach ($contacts as $contact)
                        @php
                            $linkedClient = $contact->resolveLinkedClient();
                            $primaryZone = $linkedClient?->zones?->first()?->zone;
                        @endphp
                        <tr class="hover:bg-gray-100">
                            <td class="p-4 whitespace-nowrap">
                                <a href="{{ route('contacts.show', $contact) }}" class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-800">
                                    @svg('heroicon-o-eye', 'w-4 h-4')
                                    Ver
                                </a>
                            </td>
                            <td class="p-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                {{ $contact->nit ?? '-' }}
                            </td>
                            <td class="p-4 text-sm font-normal text-gray-500">
                                <span class="font-medium text-gray-900 truncate block max-w-[280px]" title="{{ $contact->name }}">
                                    {{ \Illuminate\Support\Str::limit($contact->name, 55) }}
                                </span>
                            </td>
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                {{ $primaryZone ?: 'por asignar' }}
                            </td>
                            <td class="p-4 text-sm whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full {{ $contact->transmit_status_color }}">
                                    {{ $contact->transmit_status_label }}
                                </span>
                            </td>
                            <td class="p-4 text-sm whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full {{ $contact->workflow_status_color }}">
                                    {{ $contact->workflow_status_label }}
                                </span>
                            </td>
                        </tr>

                        @endforeach
            


                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


{{ $contacts->links() }} 

@endsection
