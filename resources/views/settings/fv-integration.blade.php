@extends('layouts.admin')

@section('content')

    @if (session('success'))
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
        <div class="w-full">
            <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Integración FV (Dynamics 365)</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Salud y registros del webservice CreateSalesOrder usado por los pedidos Express 48h / Coordinadora.
                        <a href="{{ route('settings.index') }}" class="text-blue-600 hover:underline ml-2">Volver a configuraciones</a>
                    </p>
                </div>
                <form action="{{ route('settings.fv-integration.test') }}" method="POST">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                        @svg('heroicon-o-signal', 'w-4 h-4 mr-2')
                        Probar conexión
                    </button>
                </form>
            </div>

            {{-- Health --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                <div class="border border-gray-200 rounded-lg p-4">
                    <h2 class="text-base font-semibold text-gray-900 mb-3">Configuración</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Endpoint</dt>
                            <dd class="text-right break-all">
                                @if ($health['endpoint'])
                                    <span class="text-gray-900">{{ $health['endpoint'] }}</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">No configurado</span>
                                    <div class="text-xs text-red-600 mt-1">{{ $health['endpoint_error'] }}</div>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">SOAPAction</dt>
                            <dd class="text-gray-900 text-right break-all">{{ $health['soap_action'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Compañía / Origen venta</dt>
                            <dd class="text-gray-900">{{ $health['company'] }} / {{ $health['origen_venta'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Location / Sequence group</dt>
                            <dd class="text-gray-900">{{ $health['location_invoice'] }} / {{ $health['num_sequence_group'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Almacén por defecto</dt>
                            <dd>
                                @if ($health['default_warehouse'])
                                    <span class="text-gray-900">{{ $health['default_warehouse'] }}</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800" title="Se usará solo el mapeo zona → bodega">Sin fallback</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="border border-gray-200 rounded-lg p-4">
                    <h2 class="text-base font-semibold text-gray-900 mb-3">Estado</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Token de Microsoft</dt>
                            <dd>
                                @if ($health['token_present'])
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Presente</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Ausente</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Última prueba de conexión</dt>
                            <dd class="text-right">
                                @if ($health['last_check'])
                                    @if ($health['last_check']['ok'] ?? false)
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                            OK · HTTP {{ $health['last_check']['http_status'] }} · {{ $health['last_check']['latency_ms'] }} ms
                                        </span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Falló</span>
                                        <div class="text-xs text-red-600 mt-1 break-all">{{ $health['last_check']['error'] ?? '' }}</div>
                                    @endif
                                    <div class="text-xs text-gray-500 mt-1">{{ $health['last_check']['checked_at'] ?? '' }}</div>
                                @else
                                    <span class="text-gray-500">Nunca ejecutada</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Última FV creada</dt>
                            <dd class="text-right">
                                @if ($lastFvOrder)
                                    <a href="{{ route('orders.edit', $lastFvOrder) }}" class="text-blue-600 hover:underline font-medium">{{ $lastFvOrder->fv_number }}</a>
                                    <div class="text-xs text-gray-500 mt-1">Pedido #{{ $lastFvOrder->id }} · {{ $lastFvOrder->coordinadora_status_at ?? $lastFvOrder->updated_at }}</div>
                                @else
                                    <span class="text-gray-500">Ninguna</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</div>
                    <div class="text-sm text-gray-500">Pedidos 48h Coordinadora</div>
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="text-2xl font-bold text-green-600">{{ $stats['with_fv'] }}</div>
                    <div class="text-sm text-gray-500">Con FV creada</div>
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="text-2xl font-bold text-yellow-600">{{ $stats['pending'] }}</div>
                    <div class="text-sm text-gray-500">Pendientes / en espera</div>
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="text-2xl font-bold text-red-600">{{ $stats['errors'] }}</div>
                    <div class="text-sm text-gray-500">Con error</div>
                </div>
            </div>

            {{-- Filters --}}
            <form method="GET" class="mb-4 flex flex-col sm:flex-row gap-3">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Pedido # o número FV"
                    class="border border-gray-300 rounded-lg text-sm px-3 py-2 w-full sm:w-64">
                <select name="status" class="border border-gray-300 rounded-lg text-sm px-3 py-2 w-full sm:w-56">
                    <option value="">Todos los estados</option>
                    <option value="processed" @selected(request('status') === 'processed')>Procesados</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pendientes / en espera</option>
                    <option value="error" @selected(request('status') === 'error')>Con error</option>
                    <option value="no_fv" @selected(request('status') === 'no_fv')>Sin FV</option>
                </select>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-lg hover:bg-gray-900">Filtrar</button>
                @if (request()->hasAny(['search', 'status']))
                    <a href="{{ route('settings.fv-integration') }}" class="px-4 py-2 text-sm text-gray-600 hover:underline self-center">Limpiar</a>
                @endif
            </form>

            {{-- Orders table --}}
            <div class="overflow-x-auto border border-gray-200 rounded-lg mb-6">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Pedido</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Fecha</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Cliente</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">FV</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Guía</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Estado</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Payloads</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($orders as $order)
                            <tr class="hover:bg-gray-50 align-top">
                                <td class="p-4 whitespace-nowrap">
                                    <a href="{{ route('orders.edit', $order) }}" class="text-blue-600 hover:underline font-medium">#{{ $order->id }}</a>
                                </td>
                                <td class="p-4 whitespace-nowrap text-sm text-gray-900">{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="p-4 text-sm text-gray-900">{{ $order->user?->name ?? '—' }}</td>
                                <td class="p-4 whitespace-nowrap text-sm">
                                    @if ($order->fv_number)
                                        <span class="font-medium text-gray-900">{{ $order->fv_number }}</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Sin FV</span>
                                    @endif
                                </td>
                                <td class="p-4 whitespace-nowrap text-sm text-gray-900">{{ $order->coordinadora_guide_number ?? '—' }}</td>
                                <td class="p-4 whitespace-nowrap text-sm">
                                    <x-order-status :status="$order->status_id" />
                                    @if ($order->coordinadora_status_text)
                                        <div class="text-xs text-gray-500 mt-1">{{ $order->coordinadora_status_text }}</div>
                                    @endif
                                </td>
                                <td class="p-4 text-sm">
                                    @if ($order->fv_request_payload || $order->fv_response_payload)
                                        <details>
                                            <summary class="cursor-pointer text-blue-600 hover:underline text-xs">Ver FV</summary>
                                            @php
                                                $fvRequest = json_decode($order->fv_request_payload ?? '', true);
                                                $fvResponse = json_decode($order->fv_response_payload ?? '', true);
                                            @endphp
                                            @if (is_array($fvRequest) && !empty($fvRequest['xml']))
                                                <div class="text-xs font-semibold text-gray-700 mt-2">Request XML</div>
                                                <pre class="mt-1 p-2 bg-gray-50 border border-gray-200 rounded text-xs overflow-x-auto max-w-md max-h-64">{{ $fvRequest['xml'] }}</pre>
                                            @elseif ($fvRequest)
                                                <div class="text-xs font-semibold text-gray-700 mt-2">Request</div>
                                                <pre class="mt-1 p-2 bg-gray-50 border border-gray-200 rounded text-xs overflow-x-auto max-w-md max-h-64">{{ json_encode($fvRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                            @endif
                                            @if ($fvResponse)
                                                <div class="text-xs font-semibold text-gray-700 mt-2">Response</div>
                                                <pre class="mt-1 p-2 bg-gray-50 border border-gray-200 rounded text-xs overflow-x-auto max-w-md max-h-64">{{ json_encode(collect($fvResponse)->except('raw')->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                            @endif
                                        </details>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-6 text-center text-sm text-gray-500">No hay pedidos Express 48h / Coordinadora que coincidan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mb-6">
                {{ $orders->links() }}
            </div>

            {{-- File logs --}}
            <div class="border border-gray-200 rounded-lg p-4">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Registros recientes (soap-requests.log)</h2>
                <p class="text-xs text-gray-500 mb-3">Últimas entradas de FV encontradas en los archivos de log SOAP (más recientes primero).</p>
                @if (count($fileLogs))
                    <pre class="p-3 bg-gray-900 text-gray-100 rounded text-xs overflow-x-auto max-h-96">@foreach ($fileLogs as $line){{ $line }}
@endforeach</pre>
                @else
                    <p class="text-sm text-gray-500">No se encontraron entradas de FV en los logs recientes.</p>
                @endif
            </div>
        </div>
    </div>

@endsection
