@extends('layouts.admin')

@section('content')

<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Retenciones</h1>
            <p class="text-sm text-gray-500 mt-1">Configura las reglas de retención por grupo de impuestos y tipo de producto</p>
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100">
            <div class="flex items-center mb-4 sm:mb-0">
                <x-search :home="route('admin.retentions.index')" />
            </div>
            <a href="{{ route('admin.retentions.create') }}"
                class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5">
                Nueva regla
            </a>
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
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Grupo Impuesto</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Tipo Producto</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Base Rte Fuente</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">% Rte Fuente</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Base Rte IVA</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">% Rte IVA</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Estado</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($rules as $rule)
                        <tr class="hover:bg-gray-100">
                            <td class="p-4 text-sm font-semibold text-gray-900 whitespace-nowrap">
                                {{ $rule->tax_group }}
                            </td>
                            <td class="p-4 text-sm text-gray-700 whitespace-nowrap">
                                {{ \App\Models\RetentionRule::PRODUCT_TYPES[$rule->product_type] ?? $rule->product_type }}
                            </td>
                            <td class="p-4 text-sm text-gray-700 whitespace-nowrap">
                                ${{ number_format($rule->base_rte_fuente, 0, ',', '.') }}
                            </td>
                            <td class="p-4 text-sm text-gray-700 whitespace-nowrap">
                                {{ $rule->pct_rte_fuente > 0 ? number_format($rule->pct_rte_fuente, 2) . '%' : '—' }}
                            </td>
                            <td class="p-4 text-sm text-gray-700 whitespace-nowrap">
                                ${{ number_format($rule->base_rte_iva, 0, ',', '.') }}
                            </td>
                            <td class="p-4 text-sm text-gray-700 whitespace-nowrap">
                                {{ $rule->pct_rte_iva > 0 ? number_format($rule->pct_rte_iva, 2) . '%' : '—' }}
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                @if($rule->active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Activo</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Inactivo</span>
                                @endif
                            </td>
                            <td class="p-4 space-x-2 whitespace-nowrap text-end">
                                <a href="{{ route('admin.retentions.edit', $rule) }}"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"></path>
                                        <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"></path>
                                    </svg>
                                    Editar
                                </a>
                                <form action="{{ route('admin.retentions.destroy', $rule) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar esta regla de retención?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white bg-red-600 rounded-lg hover:bg-red-800 focus:ring-4 focus:ring-red-300">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="p-6 text-center text-gray-500">
                                No hay reglas de retención configuradas.
                                <a href="{{ route('admin.retentions.create') }}" class="text-blue-600 hover:underline">Crear la primera regla</a>.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{ $rules->links() }}

@endsection
