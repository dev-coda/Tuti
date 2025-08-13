@props(['product', 'bodegaCode' => null])
@php
    $inventoryEnabled = \App\Models\Setting::getByKey('inventory_enabled');
    $showInventory = ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true);
@endphp
<div class="border rounded p-3">
    <a href='{{route('product', $product->slug)}}' class="block">
        <div class="flex items-center space-x-3">
            <div class="w-16 h-16 bg-white rounded-sm flex items-center justify-center">
                <img src="{{asset('storage/'.$product->image)}}" alt="{{$product->name}}" class="w-full h-full object-contain">
            </div>
            <div class="flex-1">
                <h3 class="font-semibold text-sm">{{$product->name}}</h3>
                @php
                    $available = $product->getInventoryForBodega($bodegaCode);
                    $mdtat = $product->getInventoryForMdtat();
                @endphp
                @if($showInventory)
                    @if($available <= 0)
                        <span class="text-xs bg-red-50 text-red-600 rounded px-2 py-0.5">Agotado</span>
                    @endif
                    <p class="text-xs {{ $available > 5 ? 'text-green-600' : 'text-red-600' }}">Inventario: {{ $available }}</p>
                    <p class="text-xs text-gray-600">Inventario (MDTAT): {{ $mdtat }}</p>
                @endif
            </div>
        </div>
    </a>
</div>