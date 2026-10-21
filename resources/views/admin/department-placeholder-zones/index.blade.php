@extends('layouts.admin')

@section('title', 'Zonas placeholder por departamento')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-1 xl:gap-4">
    <div class="mb-4 col-span-full">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Zonas placeholder por departamento</h1>
        <p class="text-sm text-gray-500 mt-1">
            Lista maestra de zona y ruta de la ciudad cabecera de cada departamento.
            Se envía en checkout y transmisión cuando el cliente no tiene sucursal real.
        </p>
    </div>

    @if(session('success'))
        <div class="p-4 mb-4 text-sm text-green-800 bg-green-100 border border-green-200 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <form action="{{ route('department-placeholder-zones.update') }}" method="POST">
            @csrf
            @method('PUT')
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Departamento</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ciudad cabecera</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zona</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ruta</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Día</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">DANE</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dirección enviada</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Activa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($rows as $index => $row)
                            <tr class="{{ $row->isReady() ? '' : 'bg-amber-50' }}">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ $row->state?->name ?? '—' }}
                                    <input type="hidden" name="rows[{{ $index }}][id]" value="{{ $row->id }}">
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row->city?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <input type="text" name="rows[{{ $index }}][zone]" value="{{ $row->zone }}"
                                           maxlength="8" class="w-24 rounded-lg border-gray-300 text-sm font-mono focus:border-orange-500 focus:ring-orange-500"
                                           placeholder="933">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" name="rows[{{ $index }}][route]" value="{{ $row->route }}"
                                           maxlength="8" class="w-24 rounded-lg border-gray-300 text-sm font-mono focus:border-orange-500 focus:ring-orange-500"
                                           placeholder="1234">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" name="rows[{{ $index }}][day]" value="{{ $row->day }}"
                                           class="w-28 rounded-lg border-gray-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                                           placeholder="1">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" name="rows[{{ $index }}][dane_code]" value="{{ $row->dane_code }}"
                                           maxlength="16" class="w-28 rounded-lg border-gray-300 text-sm font-mono focus:border-orange-500 focus:ring-orange-500"
                                           placeholder="05001000">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" name="rows[{{ $index }}][address]" value="{{ $row->address }}"
                                           class="w-full min-w-[12rem] rounded-lg border-gray-300 text-sm focus:border-orange-500 focus:ring-orange-500">
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input type="hidden" name="rows[{{ $index }}][enabled]" value="0">
                                    <input type="checkbox" name="rows[{{ $index }}][enabled]" value="1"
                                           class="rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                                           @checked($row->enabled)>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">
                                    No hay ciudades preferidas para armar el catálogo. Marca ciudades cabecera como preferidas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($rows->isNotEmpty())
                <div class="px-4 py-4 border-t border-gray-200 flex items-center justify-between gap-3">
                    <p class="text-xs text-gray-500">
                        Filas en ámbar aún no tienen zona y ruta: no se usarán en checkout.
                    </p>
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-orange-600 rounded-lg hover:bg-orange-700">
                        Guardar lista maestra
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>
@endsection
