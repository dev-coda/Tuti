@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white border border-gray-200 rounded-2xl shadow-sm p-4 sm:p-6']) }}>
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-900">{{ $title }}</p>
            @if(filled($subtitle))
                <p class="text-xs text-gray-500 mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($badge)
            <div class="self-start">{!! $badge !!}</div>
        @endisset
    </div>

    @isset($body)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 text-sm">
            {!! $body !!}
        </div>
    @endisset

    @isset($extra)
        {!! $extra !!}
    @endisset

    @isset($actions)
        <div class="mt-4 pt-4 border-t border-gray-100 flex flex-col sm:flex-row sm:flex-wrap sm:justify-end gap-2">
            {!! $actions !!}
        </div>
    @endisset
</div>
