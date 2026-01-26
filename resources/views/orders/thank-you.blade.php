@extends('layouts.page')

@section('head')
@include('elements.seo', ['title'=>'¡Gracias por tu compra!' ])
@endsection

@section('content')
<div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto">
        <!-- Success Icon -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-24 h-24 bg-green-100 rounded-full mb-6">
                <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-3">
                ¡Gracias por tu compra!
            </h1>
            <p class="text-lg text-gray-600">
                Tu pedido ha sido recibido y está siendo procesado.
            </p>
        </div>

        <!-- Order Summary Card -->
        <div class="bg-white rounded-lg shadow-sm border-l-4 border-orange-500 p-6 sm:p-8 mb-6">
            <!-- Header with Icon -->
            <div class="flex items-start mb-6">
                <div class="flex-shrink-0 w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Resumen del Pedido</h2>
                    <p class="text-sm text-gray-500">Pedido #{{ $order->id }}</p>
                </div>
            </div>

            <!-- Order Details -->
            <div class="space-y-4">
                <div class="flex justify-between items-center py-3 border-b border-gray-100">
                    <span class="text-sm text-gray-600">Número de pedido</span>
                    <span class="text-sm font-semibold text-gray-900">#{{ $order->id }}</span>
                </div>

                <div class="flex justify-between items-center py-3 border-b border-gray-100">
                    <span class="text-sm text-gray-600">Fecha del pedido</span>
                    <span class="text-sm font-semibold text-gray-900">{{ $order->created_at->locale('es')->translatedFormat('d \d\e F \d\e Y') }}</span>
                </div>

                @if($order->delivery_date && $order->delivery_method)
                <div class="flex justify-between items-center py-3 border-b border-gray-100">
                    <span class="text-sm text-gray-600">Método de envío</span>
                    <span class="text-sm font-semibold text-gray-900">{{ $order->delivery_method }}</span>
                </div>
                
                <div class="flex justify-between items-center py-3 border-b border-gray-100">
                    <span class="text-sm text-gray-600">Entrega estimada</span>
                    <span class="text-sm font-semibold text-gray-900">{{ \Carbon\Carbon::parse($order->delivery_date)->locale('es')->translatedFormat('l d \d\e F \d\e Y') }}</span>
                </div>
                @endif

                <div class="flex justify-between items-center pt-4">
                    <span class="text-base font-medium text-gray-900">Total</span>
                    <span class="text-2xl font-bold text-orange-600">${{ number_format($order->total, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- Email Confirmation Message -->
        @if($order->user && $order->user->email)
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-blue-800 text-center">
                Hemos enviado un correo de confirmación a 
                <a href="mailto:{{ $order->user->email }}" class="font-semibold hover:underline">{{ $order->user->email }}</a>
                con los detalles de tu pedido.
            </p>
        </div>
        @endif

        <!-- Action Buttons -->
        <div class="space-y-3">
            <!-- Primary Button -->
            <a 
                href="/ordenes/{{ $order->id }}" 
                class="w-full flex items-center justify-center px-6 py-3 bg-orange-600 text-white font-medium rounded-lg hover:bg-orange-700 transition-colors duration-200 shadow-sm"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                Ver Detalles del Pedido
            </a>

            <!-- Secondary Buttons -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a 
                    href="{{ route('home') }}" 
                    class="flex items-center justify-center px-6 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors duration-200"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Seguir Comprando
                </a>

                <a 
                    href="/ordenes" 
                    class="flex items-center justify-center px-6 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors duration-200"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Mis Pedidos
                </a>
            </div>
        </div>

        <!-- Additional Info (Optional) -->
        @if($order->status_id === \App\Models\Order::STATUS_WAITING && $order->scheduled_transmission_date)
        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex">
                <svg class="w-5 h-5 text-yellow-600 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                <div>
                    <h3 class="text-sm font-medium text-yellow-800">Pedido programado</h3>
                    <p class="text-sm text-yellow-700 mt-1">
                        Tu pedido será enviado el {{ \Carbon\Carbon::parse($order->scheduled_transmission_date)->format('d/m/Y') }}.
                    </p>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
