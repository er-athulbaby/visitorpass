@props(['title', 'subtitle' => null])

{{-- Page title with an optional subtitle; the slot holds the page's main actions. --}}
<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between md:mb-8']) }}>
    <div class="min-w-0">
        <h1 class="text-page-title text-on-surface">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-body-md text-on-surface-variant">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
