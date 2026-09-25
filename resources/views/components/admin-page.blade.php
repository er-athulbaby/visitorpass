@php
    $mode = \App\Models\Setting::current()?->deployment_mode;
    $tabs = $mode === 'company'
        ? [['admin.departments.index', 'admin.departments.*', __('Departments')], ['admin.employees.index', 'admin.employees.*', __('Employees')]]
        : [['admin.companies.index', 'admin.companies.*', __('Companies')]];
    $tabs[] = ['admin.users.index', 'admin.users.*', __('Users')];
    $tabs[] = ['admin.settings.edit', 'admin.settings.*', __('Settings')];
    $current = collect($tabs)->first(fn ($tab) => request()->routeIs($tab[1]))[2] ?? null;
@endphp

<x-sidebar-layout>
    <x-slot:title>{{ $current ? $current.' · ' : '' }}{{ __('Administration') }}</x-slot:title>

    <div class="mx-auto max-w-6xl px-4 py-6 md:px-8 md:py-10">
        <h1 class="text-headline-md text-on-surface">{{ __('Administration') }}</h1>

        <nav aria-label="{{ __('Administration') }}" class="mt-4 flex gap-1 overflow-x-auto border-b border-outline-variant">
            @foreach ($tabs as [$route, $pattern, $label])
                <a href="{{ route($route) }}"@if (request()->routeIs($pattern)) aria-current="page"@endif class="whitespace-nowrap border-b-2 px-4 py-2 text-label-md {{ request()->routeIs($pattern) ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface' }}">{{ $label }}</a>
            @endforeach
        </nav>

        @if (session('status'))
            <p class="mt-4 text-green-700" role="status">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="mt-4 text-red-600" role="alert">{{ session('error') }}</p>
        @endif

        <div class="mt-6 max-w-3xl">
            {{ $slot }}
        </div>
    </div>
</x-sidebar-layout>
