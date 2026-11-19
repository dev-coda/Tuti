@props([
    'label',
    'span' => false,
])

<div {{ $attributes->class(['sm:col-span-2' => $span]) }}>
    <p class="text-xs text-gray-500 uppercase">{{ $label }}</p>
    <div class="font-semibold text-gray-800 break-words">{!! $slot !!}</div>
</div>
