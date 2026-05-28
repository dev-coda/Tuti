@extends('layouts.admin')


@section('content')


<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
    <div class="mb-4 flex justify-between">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Clientes</h1>
            <a href="/userexport">
                @svg('heroicon-o-arrow-down-on-square', 'w-8 h-8 text-blue-500')
            </a>
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100 ">
            <div class="flex items-center mb-4 sm:mb-0">
               <x-search :home="route('users.index')" />
            </div>
        </div>
    </div>
</div>

<div class="mx-4 mb-4 p-4 bg-slate-50 border border-slate-200 rounded-lg text-sm">
    <h2 class="font-semibold text-gray-900 mb-2">Sincronización con Dynamics (rutero)</h2>
    <p class="text-gray-600 mb-2 text-xs">Los datos de cliente (nombre, teléfonos, saldo, correo si viene en la respuesta, zonas) se actualizan desde el servicio getRuteros. También se ejecuta al crear un pedido.</p>
    <ul class="text-gray-600 space-y-1 mb-3 text-xs sm:text-sm">
        <li>
            <span class="font-medium">Sync diaria (03:20):</span>
            @if ($dailyRuteroSyncEnabled === '1' || $dailyRuteroSyncEnabled === 1 || $dailyRuteroSyncEnabled === true)
                <span class="text-green-700">activada</span>
            @else
                <span class="text-amber-700">desactivada</span> (ajuste <code class="bg-white px-1 rounded">daily_client_rutero_sync_enabled</code> en configuración)
            @endif
        </li>
        <li>
            <span class="font-medium">Última sync masiva completada:</span>
            {{ $lastRuteroBulkAtFormatted ?? 'Aún no registrada' }}
        </li>
        @if ($lastRuteroBulkSession)
            <li><span class="font-medium">Sesión:</span> <code class="text-xs bg-white px-1 rounded">{{ $lastRuteroBulkSession }}</code></li>
        @endif
    </ul>
    <div class="flex flex-wrap items-center gap-2">
        <form method="post" action="{{ route('bulk-operations.sync-clients-data') }}" class="inline">
            @csrf
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                Sincronizar ahora
            </button>
        </form>
        @if ($lastRuteroReportExists && $lastRuteroReportFilename)
            <a href="{{ route('bulk-operations.download-report', ['filename' => $lastRuteroReportFilename]) }}"
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Descargar CSV del último informe
            </a>
        @else
            <span class="text-xs text-gray-500">El CSV del informe aparecerá cuando termine el trabajo en cola.</span>
        @endif
        <a href="{{ route('bulk-operations.index') }}" class="text-sm text-blue-600 hover:underline">Ver todos los informes</a>
    </div>
</div>

<div class="flex flex-col">
    <div class="overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
            <div class="overflow-hidden shadow">
                <table class="min-w-full divide-y divide-gray-200 table-fixed ">
                    <thead class="bg-gray-100">
                        <tr>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Nombre
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Razón Social
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Teléfono
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Tipo de Cliente
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Saldo
                            </th>

                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase whitespace-nowrap">
                                Sync Dynamics
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Estado
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">

                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 ">

                        <tr class="hover:bg-gray-100">
                            @foreach ($users as $user)
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                <a class="flex flex-col text-gray-900  hover:text-blue-500" href="{{ route('users.edit', $user) }}">
                                    <span class="text-base font-semibold ">
                                        {{ $user->name }}
                                    </span>
                                     <small class="text-xs  text-slate-500">
                                        {{ $user->document }}
                                    </small>

                                </a>
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                {{ $user->business_name ?: '-' }}
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                {{ $user->phone ?: $user->mobile_phone ?: '-' }}
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                {{ $user->customer_type ?: '-' }}
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                ${{ number_format($user->balance ?? 0, 0, ',', '.') }}
                            </td>

                            <td class="p-4 text-xs text-gray-600 whitespace-nowrap">
                                @if ($user->rutero_synced_at)
                                    {{ $user->rutero_synced_at->timezone(config('app.timezone'))->format('d/m/y H:i') }}
                                @else
                                    —
                                @endif
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                @if($user->isProspectClient())
                                    <span class="px-2 py-1 text-xs font-semibold text-indigo-900 bg-indigo-100 rounded">Prospecto</span>
                                @elseif($user->isPendingClient())
                                    <span class="px-2 py-1 text-xs font-semibold text-amber-900 bg-amber-100 rounded">Pendiente</span>
                                @elseif($user->isCliente())
                                    <span class="px-2 py-1 text-xs font-semibold text-emerald-900 bg-emerald-100 rounded">Cliente</span>
                                @endif
                                <div class="mt-1">
                                @if($user->is_locked)
                                    <span class="px-2 py-1 text-xs font-semibold text-white bg-red-500 rounded">Bloqueado</span>
                                @elseif($user->customer_status == 'No')
                                    <span class="px-2 py-1 text-xs font-semibold text-white bg-yellow-500 rounded">Inactivo</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold text-white bg-green-500 rounded">Activo</span>
                                @endif
                                </div>
                            </td>




                            <td class="p-4 space-x-2 whitespace-nowrap text-end">
                                <a href="{{ route('users.edit', $user) }}"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 ">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z">
                                        </path>
                                        <path fill-rule="evenodd"
                                            d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    Editar
                                </a>
                              
                            </td>
                        </tr>

                        @endforeach
            


                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


{{ $users->withQueryString()->links() }} 











@endsection
