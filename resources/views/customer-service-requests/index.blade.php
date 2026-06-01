@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white border-b border-gray-200">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">PQRS Servicio al Cliente</h1>
    </div>

    <form method="GET" action="{{ route('admin.customer-service-requests.index') }}" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
            <select name="type" class="border-gray-300 rounded-lg text-sm">
                <option value="">Todos</option>
                @foreach(\App\Models\CustomerServiceRequest::REQUEST_TYPES as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="border-gray-300 rounded-lg text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Filtrar</button>
        <a href="{{ route('admin.customer-service-requests.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">Limpiar</a>
    </form>
</div>

<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 bg-white">
        <thead class="bg-gray-100">
            <tr>
                <th class="p-4 text-xs text-left font-medium text-gray-500 uppercase">Acciones</th>
                <th class="p-4 text-xs text-left font-medium text-gray-500 uppercase">Fecha</th>
                <th class="p-4 text-xs text-left font-medium text-gray-500 uppercase">Nombre</th>
                <th class="p-4 text-xs text-left font-medium text-gray-500 uppercase">Tipo</th>
                <th class="p-4 text-xs text-left font-medium text-gray-500 uppercase">Asunto</th>
                <th class="p-4 text-xs text-left font-medium text-gray-500 uppercase">Estado</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($requests as $requestEntry)
                <tr class="hover:bg-gray-50">
                    <td class="p-4 text-sm">
                        <a href="{{ route('admin.customer-service-requests.show', $requestEntry) }}" class="text-blue-600 hover:text-blue-800 font-medium">Ver</a>
                    </td>
                    <td class="p-4 text-sm text-gray-700">{{ $requestEntry->created_at->format('d/m/Y H:i') }}</td>
                    <td class="p-4 text-sm text-gray-900">{{ $requestEntry->full_name }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ $requestEntry->request_type_label }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ \Illuminate\Support\Str::limit($requestEntry->subject, 80) }}</td>
                    <td class="p-4 text-sm">
                        @if($requestEntry->read_at)
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Leído</span>
                        @else
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800">Nuevo</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-6 text-center text-sm text-gray-500">No hay solicitudes PQRS registradas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $requests->links() }}
</div>
@endsection
