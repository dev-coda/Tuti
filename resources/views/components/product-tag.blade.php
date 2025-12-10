@props(['product'])

@php
    $tag = $product->getActiveTag();
@endphp

@if($tag)
    <div class="absolute top-2 right-2 z-10 px-2 py-1 text-xs font-semibold text-white bg-orange-600 rounded shadow-lg">
        {{ $tag->content }}
    </div>
@endif

