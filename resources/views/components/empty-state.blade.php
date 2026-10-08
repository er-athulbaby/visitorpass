@props(['icon' => 'inbox', 'title', 'text' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-surface-container text-on-surface-variant">
        <x-icon :name="$icon" class="text-[24px]" />
    </span>
    <p class="mt-4 text-label-md font-semibold text-on-surface">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-sm text-body-sm text-on-surface-variant">{{ $text }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
