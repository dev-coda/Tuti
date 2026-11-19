@props([
    'href' => null,
    'variant' => 'secondary',
    'type' => 'submit',
])

@php
    $classes = match ($variant) {
        'primary' => 'w-full sm:w-auto px-3 py-2 bg-orange-600 text-white text-xs font-medium rounded-lg hover:bg-orange-700 transition-colors text-center',
        'accent' => 'w-full sm:w-auto px-3 py-2 border border-orange-300 text-orange-700 text-xs font-medium rounded-lg hover:bg-orange-50 transition-colors text-center',
        default => 'w-full sm:w-auto px-3 py-2 border border-gray-300 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 transition-colors text-center',
    };
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
