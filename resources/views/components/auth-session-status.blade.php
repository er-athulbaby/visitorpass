@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'alert alert-success']) }} role="status">
        <x-icon name="check_circle" class="shrink-0" />
        <span>{{ $status }}</span>
    </div>
@endif
