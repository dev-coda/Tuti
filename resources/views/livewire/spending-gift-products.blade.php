<div>
    <select wire:change.debounce.200ms="add" wire:model="selectedProduct" class="shadow-sm bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm focus:ring-primary-500 focus:border-primary-500 block w-full p-2 text-base rounded-sm">
        <option value="">Seleccione</option>
        @foreach ($products as $product)
            <option value="{{ $product->id }}">{{ $product->name }}</option>
        @endforeach
    </select>

    <div class="flex flex-col space-y-2 mt-4">
        @forelse ($attached as $product)
            <div class="flex items-center space-x-1">
                <button wire:click="remove({{ $product->id }})" type="button" class="hover:bg-gray-400 p-1 rounded">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                </button>
                <span class="text-sm font-medium text-gray-900">{{ $product->name }}</span>
            </div>
        @empty
            <p class="text-sm text-gray-500">Ningún producto asociado. Sin productos, esta regla no entrega el obsequio.</p>
        @endforelse
    </div>
</div>
