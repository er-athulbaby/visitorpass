@props(['label', 'value', 'icon', 'tone' => 'neutral', 'hint' => null, 'live' => false])

@php
    $iconTone = [
        'primary' => 'text-primary bg-[var(--color-primary-soft)]',
        'success' => 'text-success bg-success-container',
        'info' => 'text-[#0369A1] bg-info-container',
        'neutral' => 'text-on-surface-variant bg-surface-container',
    ][$tone];
@endphp

<div {{ $attributes->merge(['class' => 'card p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-label-md text-on-surface-variant">{{ $label }}</p>
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $iconTone }}">
            <x-icon :name="$icon" />
        </span>
    </div>
    <p class="mt-3 flex items-center gap-2 text-metric text-on-surface tabular">
        {{ $value }}
        @if ($live && $value > 0)
            <span class="relative flex h-2.5 w-2.5" aria-hidden="true">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success opacity-50 motion-reduce:animate-none"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-success"></span>
            </span>
        @endif
    </p>
    @if ($hint)
        <p class="mt-1 text-[13px] text-on-surface-variant">{{ $hint }}</p>
    @endif
</div>
