@props(['product'])

@php
    $tags = $product->getActiveTags();
@endphp

@if(!empty($tags))
    <div class="absolute top-2 right-2 z-10 flex flex-col gap-1">
        @foreach($tags as $tag)
            <div class="px-2 py-1 text-xs font-semibold text-white rounded shadow-lg
                {{ $tag['type'] === 'auto_nuevo' ? 'bg-green-600' : ($tag['type'] === 'auto_descuento' ? 'bg-red-600' : 'bg-orange-600') }}">
                {{ $tag['content'] }}
            </div>
        @endforeach
    </div>
@endif

