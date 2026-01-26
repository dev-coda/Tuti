@extends('layouts.page')

@section('head')
@include('elements.seo', ['title'=>'Pedido #'.$order->id ])
@endsection

@section('content')
<section class="w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Back Button -->
    <a href="{{ route('clients.orders.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 mb-4">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Volver a Mis Pedidos
    </a>

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900">Pedido #{{ $order->id }}</h1>
            <p class="text-sm text-gray-500 mt-1">Realizado el {{ $order->created_at->subHour(5)->format('d M Y') }}</p>
        </div>
        <div>
            <x-order-status :status="$order->status_id" />
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content (Left Column) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Order Status Timeline -->
            <div class="bg-white border-2 border-orange-500 rounded-2xl p-5 sm:p-6">
                <h2 class="flex items-center gap-2 text-lg font-semibold text-gray-900 mb-6">
                    <svg class="w-5 h-5 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                    </svg>
                    Estado del Pedido
                </h2>
                
                @php
                    // Define status configurations
                    $statusLabels = [
                        0 => 'Pendiente',
                        1 => 'Procesado',
                        2 => 'Error',
                        3 => 'Error WS',
                    ];
                    
                    $statusColors = [
                        0 => 'blue',   // Pending - blue
                        1 => 'green',  // Processed - green
                        2 => 'red',    // Error - red
                        3 => 'red',    // Error WS - red
                    ];
                    
                    $currentStatusLabel = $statusLabels[$order->status_id] ?? 'Desconocido';
                    $currentStatusColor = $statusColors[$order->status_id] ?? 'gray';
                    
                    // Always show these 3 steps
                    $statuses = [
                        [
                            'label' => 'Pedido realizado',
                            'date' => $order->created_at->subHour(5)->format('d M Y, g:i A'),
                            'completed' => true,
                            'color' => 'green'
                        ],
                        [
                            'label' => 'Pedido Pendiente',
                            'date' => $order->created_at->subHour(5)->format('d M Y, g:i A'),
                            'completed' => true,
                            'color' => 'green'
                        ],
                        [
                            'label' => $currentStatusLabel,
                            'date' => $order->updated_at->subHour(5)->format('d M Y, g:i A'),
                            'completed' => true,
                            'color' => $currentStatusColor
                        ],
                    ];
                @endphp

                <!-- Desktop Timeline (Horizontal) -->
                <div class="hidden md:block">
                    <div class="flex items-center justify-between mb-4">
                        @foreach($statuses as $index => $status)
                            <div class="flex-1 {{ $index < count($statuses) - 1 ? '' : 'flex-none' }}">
                                <div class="flex items-center">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-full 
                                        @if($status['color'] === 'green') bg-green-500
                                        @elseif($status['color'] === 'blue') bg-blue-500
                                        @elseif($status['color'] === 'red') bg-red-500
                                        @else bg-gray-300
                                        @endif">
                                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    @if($index < count($statuses) - 1)
                                        <div class="flex-1 h-1 mx-2 
                                            @if($statuses[$index + 1]['color'] === 'green') bg-green-500
                                            @elseif($statuses[$index + 1]['color'] === 'blue') bg-blue-500
                                            @elseif($statuses[$index + 1]['color'] === 'red') bg-red-500
                                            @else bg-gray-300
                                            @endif"></div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex items-start justify-between">
                        @foreach($statuses as $status)
                            <div class="flex-1 {{ $loop->last ? 'text-right' : '' }}">
                                <p class="text-sm font-medium text-gray-900">{{ $status['label'] }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $status['date'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Mobile Timeline (Vertical) -->
                <div class="md:hidden space-y-4">
                    @foreach($statuses as $status)
                        <div class="flex items-start gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full flex-shrink-0
                                @if($status['color'] === 'green') bg-green-500
                                @elseif($status['color'] === 'blue') bg-blue-500
                                @elseif($status['color'] === 'red') bg-red-500
                                @else bg-gray-300
                                @endif">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ $status['label'] }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $status['date'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Products -->
            <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6">
                <h2 class="flex items-center gap-2 text-lg font-semibold text-gray-900 mb-4">
                    <svg class="w-5 h-5 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" />
                    </svg>
                    Productos
                </h2>
                
                <div class="space-y-4">
                    @foreach ($order->products as $product)
                        <div class="flex gap-4 pb-4 border-b border-gray-200 last:border-0 last:pb-0">
                            <div class="w-20 h-20 bg-gray-100 rounded-lg flex items-center justify-center overflow-hidden flex-shrink-0">
                                @if($product->product->images && $product->product->images->first())
                                    <img src="{{ asset('storage/'.$product->product->images->first()->path) }}" alt="{{ $product->product->name }}" class="w-full h-full object-contain">
                                @else
                                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-semibold text-gray-900">{{ $product->product->name }}</h3>
                                <p class="text-sm text-gray-500 mt-1">${{ number_format($product->price, 0) }}</p>
                                <p class="text-sm text-gray-500">Cantidad: {{ $product->quantity }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-base font-semibold text-orange-600">${{ number_format($product->price * $product->quantity, 0) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Shipping Method -->
            @if($order->delivery_method)
            <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6">
                <h2 class="flex items-center gap-2 text-lg font-semibold text-gray-900 mb-4">
                    <svg class="w-5 h-5 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                        <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z" />
                    </svg>
                    Método de Entrega
                </h2>
                
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                            <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-semibold text-gray-900">{{ $order->delivery_method }}</p>
                        @if($order->delivery_date)
                            <p class="text-sm text-orange-600 font-medium mt-1">{{ \Carbon\Carbon::parse($order->delivery_date)->format('l d \d\e F') }}</p>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar (Right Column) -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Order Summary -->
            <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Resumen del Pedido</h2>
                
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="text-gray-900 font-medium">${{ number_format($order->total + $order->discount, 0) }}</span>
                    </div>
                    @if($order->discount > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-green-600">Descuento</span>
                        <span class="text-green-600 font-medium">-${{ number_format($order->discount, 0) }}</span>
                    </div>
                    @endif
                    <div class="border-t border-gray-200 pt-3">
                        <div class="flex justify-between">
                            <span class="text-base font-semibold text-gray-900">Total</span>
                            <span class="text-xl font-bold text-orange-600">${{ number_format($order->total, 0) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Address -->
            <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6">
                <h2 class="flex items-center gap-2 text-lg font-semibold text-gray-900 mb-4">
                    <svg class="w-5 h-5 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                    </svg>
                    Dirección de Entrega
                </h2>
                
                <div class="space-y-2 text-sm">
                    <p class="font-semibold text-gray-900">{{ $order->user->name }}</p>
                    
                    @php
                        $zone = $order->user->zones->first();
                    @endphp
                    
                    @if($zone && $zone->address)
                        <p class="text-gray-600">{{ $zone->address }}</p>
                    @endif
                    
                    @if($order->user->city)
                        <p class="text-gray-600">{{ $order->user->city->name }}, Colombia</p>
                    @endif
                    
                    @if($order->user->phone || $order->user->mobile_phone)
                        <p class="text-gray-600 flex items-center gap-2 mt-3">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                            </svg>
                            {{ $order->user->phone ?? $order->user->mobile_phone }}
                        </p>
                    @endif
                    
                    @if($order->user->email)
                        <p class="text-gray-600 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                            </svg>
                            {{ $order->user->email }}
                        </p>
                    @endif
                </div>

                @if($zone)
                <div class="border-t border-gray-200 mt-4 pt-4">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                        <span class="w-6 h-6 rounded-full bg-orange-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        Información de Rutero
                    </p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                        @if($zone->zone)
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Zona</p>
                            <p class="font-semibold text-gray-800 break-words">{{ $zone->zone }}</p>
                        </div>
                        @endif
                        @if($zone->route)
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Ruta</p>
                            <p class="font-semibold text-gray-800 break-words">{{ $zone->route }}</p>
                        </div>
                        @endif
                        @if($zone->code)
                        <div class="col-span-2 sm:col-span-1">
                            <p class="text-xs text-gray-500 uppercase">Rutero</p>
                            <p class="font-semibold text-gray-800 break-words">{{ $zone->code }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <a href="{{ route('home') }}" class="w-full flex items-center justify-center px-6 py-3 bg-orange-600 text-white font-medium rounded-lg hover:bg-orange-700 transition-colors duration-200">
                    Volver a Comprar
                </a>
                
                <form action="{{ route('clients.orders.reorder', $order) }}" method="POST" class="w-full">
                    @csrf
                    <button type="submit" class="w-full flex items-center justify-center px-6 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors duration-200">
                        Ordenar de nuevo
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
