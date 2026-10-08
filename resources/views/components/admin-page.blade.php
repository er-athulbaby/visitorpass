@php
    $mode = \App\Models\Setting::current()?->deployment_mode;
    $tabs = $mode === 'company'
        ? [['admin.departments.index', 'admin.departments.*', __('Departments'), 'apartment'], ['admin.employees.index', 'admin.employees.*', __('Employees'), 'badge']]
        : [['admin.companies.index', 'admin.companies.*', __('Companies'), 'domain']];
    $tabs[] = ['admin.users.index', 'admin.users.*', __('Users'), 'manage_accounts'];
    $tabs[] = ['admin.settings.edit', 'admin.settings.*', __('Settings'), 'tune'];
    $current = collect($tabs)->first(fn ($tab) => request()->routeIs($tab[1]))[2] ?? null;
@endphp

<x-sidebar-layout>
    <x-slot:title>{{ $current ? $current.' · ' : '' }}{{ __('Administration') }}</x-slot:title>

    <x-page-header :title="__('Administration')" :subtitle="__('Manage the people, access and settings for this installation.')" class="!mb-5" />

    <nav aria-label="{{ __('Administration') }}" class="-mx-4 mb-6 flex gap-1 overflow-x-auto overflow-y-hidden border-b border-outline-variant px-4 md:mx-0 md:px-0">
        @foreach ($tabs as [$route, $pattern, $label, $icon])
            <a href="{{ route($route) }}"@if (request()->routeIs($pattern)) aria-current="page"@endif class="-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 px-3 py-2.5 text-label-md transition-colors duration-150 {{ request()->routeIs($pattern) ? 'border-primary font-semibold text-on-surface' : 'border-transparent text-on-surface-variant hover:border-outline-variant hover:text-on-surface' }}"><x-icon :name="$icon" class="{{ request()->routeIs($pattern) ? 'text-primary' : '' }}" />{{ $label }}</a>
        @endforeach
    </nav>

    @if (session('status'))
        <div class="alert alert-success mb-6" role="status"><x-icon name="check_circle" class="shrink-0" /> {{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error mb-6" role="alert"><x-icon name="error" class="shrink-0" /> {{ session('error') }}</div>
    @endif

    {{ $slot }}
</x-sidebar-layout>
