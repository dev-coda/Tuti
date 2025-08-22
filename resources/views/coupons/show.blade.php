@extends('layouts.admin')

@section('content')

<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl mr-4">Cupón: {{ $coupon->name }}</h1>
                @php
                    $isValid = $coupon->isValid();
                    $isExpired = now() > $coupon->valid_to;
                    $isNotYetActive = now() < $coupon->valid_from;
                @endphp
                <div @class([
                    'inline-block w-4 h-4 mr-2 rounded-full',
                    'bg-green-700' => $isValid,
                    'bg-red-700' => !$coupon->active || $isExpired,
                    'bg-yellow-500' => $coupon->active && $isNotYetActive
                ])></div>
                <span class="text-sm font-medium">
                    @if(!$coupon->active)
                        Inactivo
                    @elseif($isExpired)
                        Expirado
                    @elseif($isNotYetActive)
                        Pendiente
                    @else
                        Activo
                    @endif
                </span>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('coupons.edit', $coupon) }}"
                    class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5">
                    Editar
                </a>
                <a href="{{ route('coupons.index') }}"
                    class="text-gray-700 bg-gray-200 hover:bg-gray-300 focus:ring-4 focus:ring-gray-200 font-bold rounded-lg text-sm px-5 py-2.5">
                    Volver a la lista
                </a>
            </div>
        </div>
    </div>

    <div class="col-span-full space-y-6">
    <!-- Basic Information -->
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Información Básica</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-500">Código</label>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ $coupon->code }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Nombre</label>
                <p class="mt-1 text-lg text-gray-900">{{ $coupon->name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Tipo</label>
                <span class="mt-1 inline-flex px-2 py-1 text-xs font-medium rounded-full
                    {{ $coupon->type === 'fixed_amount' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                    {{ $coupon->type === 'fixed_amount' ? 'Monto fijo' : 'Porcentaje' }}
                </span>
            </div>
        </div>
        @if($coupon->description)
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-500">Descripción</label>
            <p class="mt-1 text-gray-900">{{ $coupon->description }}</p>
        </div>
        @endif
    </div>

    <!-- Discount Details -->
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Detalles del Descuento</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-500">Valor</label>
                <p class="mt-1 text-xl font-bold text-gray-900">
                    @if($coupon->type === 'fixed_amount')
                        ${{ number_format($coupon->value, 2) }}
                    @else
                        {{ $coupon->value }}%
                    @endif
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Se aplica a</label>
                <p class="mt-1 text-gray-900 capitalize">
                    @switch($coupon->applies_to)
                        @case('cart')
                            Todo el carrito
                            @break
                        @case('product')
                            Productos específicos
                            @break
                        @case('category')
                            Categorías específicas
                            @break
                        @case('brand')
                            Marcas específicas
                            @break
                        @case('vendor')
                            Proveedores específicos
                            @break
                        @case('customer')
                            Clientes específicos
                            @break
                        @case('customer_type')
                            Tipos de cliente
                            @break
                        @default
                            {{ $coupon->applies_to }}
                    @endswitch
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Monto mínimo</label>
                <p class="mt-1 text-gray-900">
                    @if($coupon->minimum_amount)
                        ${{ number_format($coupon->minimum_amount, 2) }}
                    @else
                        Sin límite
                    @endif
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Estado</label>
                <p class="mt-1">
                    <span @class([
                        'inline-flex px-2 py-1 text-xs font-medium rounded-full',
                        'bg-green-100 text-green-800' => $coupon->active,
                        'bg-red-100 text-red-800' => !$coupon->active
                    ])>
                        {{ $coupon->active ? 'Activo' : 'Inactivo' }}
                    </span>
                </p>
            </div>
        </div>
    </div>

    <!-- Validity Period -->
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Período de Validez</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-500">Válido desde</label>
                <p class="mt-1 text-gray-900">{{ $coupon->valid_from->format('d/m/Y H:i') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Válido hasta</label>
                <p class="mt-1 text-gray-900">{{ $coupon->valid_to->format('d/m/Y H:i') }}</p>
            </div>
        </div>
        <div class="mt-4">
            <div class="bg-gray-50 rounded-lg p-4">
                @php
                    $now = now();
                    $total_duration = $coupon->valid_from->diffInDays($coupon->valid_to);
                    $elapsed_days = $coupon->valid_from->diffInDays($now, false);
                    $remaining_days = $now->diffInDays($coupon->valid_to, false);
                    
                    if ($now < $coupon->valid_from) {
                        $progress = 0;
                        $status_text = "Comenzará en " . $now->diffInDays($coupon->valid_from) . " días";
                    } elseif ($now > $coupon->valid_to) {
                        $progress = 100;
                        $status_text = "Expiró hace " . $coupon->valid_to->diffInDays($now) . " días";
                    } else {
                        $progress = $total_duration > 0 ? min(100, ($elapsed_days / $total_duration) * 100) : 100;
                        $status_text = "Expira en " . $remaining_days . " días";
                    }
                @endphp
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Progreso de validez</span>
                    <span>{{ $status_text }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div @class([
                        'h-2 rounded-full transition-all duration-300',
                        'bg-green-600' => $progress < 75,
                        'bg-yellow-500' => $progress >= 75 && $progress < 90,
                        'bg-red-600' => $progress >= 90
                    ]) style="width: {{ $progress }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Statistics -->
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Estadísticas de Uso</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="text-center">
                <div class="text-3xl font-bold text-blue-600">{{ $coupon->current_usage }}</div>
                <div class="text-sm text-gray-500">Usos totales</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-green-600">
                    {{ $coupon->total_usage_limit ? $coupon->total_usage_limit - $coupon->current_usage : '∞' }}
                </div>
                <div class="text-sm text-gray-500">Usos restantes</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-purple-600">
                    {{ $coupon->usage_limit_per_customer ?? '∞' }}
                </div>
                <div class="text-sm text-gray-500">Límite por cliente</div>
            </div>
        </div>
        
        @if($coupon->total_usage_limit)
        <div class="mt-6">
            <div class="flex justify-between text-sm text-gray-600 mb-2">
                <span>Progreso de uso</span>
                <span>{{ $coupon->current_usage }}/{{ $coupon->total_usage_limit }}</span>
            </div>
            @php
                $usage_percentage = ($coupon->current_usage / $coupon->total_usage_limit) * 100;
            @endphp
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div @class([
                    'h-2 rounded-full transition-all duration-300',
                    'bg-green-600' => $usage_percentage < 50,
                    'bg-yellow-500' => $usage_percentage >= 50 && $usage_percentage < 80,
                    'bg-red-600' => $usage_percentage >= 80
                ]) style="width: {{ min(100, $usage_percentage) }}%"></div>
            </div>
        </div>
        @endif
    </div>

    <!-- Usage History -->
    @if($coupon->usages->count() > 0)
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Historial de Uso</h2>
        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Cliente
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Orden
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Descuento aplicado
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Fecha de uso
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($coupon->usages as $usage)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            @if($usage->user)
                                <div>
                                    <div class="font-medium">{{ $usage->user->name }}</div>
                                    <div class="text-gray-500">{{ $usage->user->email }}</div>
                                </div>
                            @else
                                <span class="text-gray-500">Usuario eliminado</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            @if($usage->order)
                                <a href="{{ route('orders.show', $usage->order) }}" class="text-blue-600 hover:text-blue-900">
                                    #{{ $usage->order->id }}
                                </a>
                            @else
                                <span class="text-gray-500">Orden eliminada</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${{ number_format($usage->discount_amount, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $usage->created_at->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="bg-white shadow rounded-lg p-6">
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Sin uso</h3>
            <p class="mt-1 text-sm text-gray-500">Este cupón aún no ha sido utilizado por ningún cliente.</p>
        </div>
    </div>
    @endif
    </div>
</div>

@endsection
