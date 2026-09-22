@php
    $navItems = [
        ['route' => 'dashboard', 'label' => __('Home'), 'icon' => 'home'],
        ['route' => 'visits.create', 'label' => __('Scan'), 'icon' => 'qr_code_scanner'],
        ['route' => 'visits.index', 'label' => __('Visitors'), 'icon' => 'groups'],
        ['route' => 'history.index', 'label' => __('History'), 'icon' => 'history'],
    ];
    $mode = \App\Models\Setting::current()?->deployment_mode;
    $isSidebar = ($variant ?? 'sidebar') === 'sidebar';
@endphp

<nav class="{{ $isSidebar ? 'flex flex-1 flex-col gap-1 p-3' : 'fixed inset-x-0 bottom-0 flex border-t border-outline-variant bg-surface-container-lowest md:hidden' }}">
    @foreach ($navItems as $item)
        <a
            href="{{ route($item['route']) }}"
            class="{{ $isSidebar
                ? 'flex items-center gap-3 rounded-lg px-4 py-3 text-label-md ' . (request()->routeIs($item['route']) ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container')
                : 'flex flex-1 flex-col items-center gap-0.5 py-2 text-label-sm ' . (request()->routeIs($item['route']) ? 'text-primary' : 'text-on-surface-variant') }}"
        >
            <x-icon :name="$item['icon']" />
            {{ $item['label'] }}
        </a>
    @endforeach

    @role('admin')
        @if ($isSidebar)
            <div class="mt-4 border-t border-outline-variant pt-4">
                <p class="px-4 text-label-sm text-on-surface-variant">{{ __('Admin') }}</p>

                @if ($mode === 'company')
                    <a href="{{ route('admin.departments.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-3 text-label-md {{ request()->routeIs('admin.departments.*') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' }}">
                        <x-icon name="apartment" /> {{ __('Departments') }}
                    </a>
                    <a href="{{ route('admin.employees.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-3 text-label-md {{ request()->routeIs('admin.employees.*') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' }}">
                        <x-icon name="badge" /> {{ __('Employees') }}
                    </a>
                @else
                    <a href="{{ route('admin.companies.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-3 text-label-md {{ request()->routeIs('admin.companies.*') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' }}">
                        <x-icon name="apartment" /> {{ __('Companies') }}
                    </a>
                @endif

                <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-3 text-label-md {{ request()->routeIs('admin.users.*') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' }}">
                    <x-icon name="group" /> {{ __('Users') }}
                </a>
                <a href="{{ route('admin.settings.edit') }}" class="flex items-center gap-3 rounded-lg px-4 py-3 text-label-md {{ request()->routeIs('admin.settings.*') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' }}">
                    <x-icon name="settings" /> {{ __('Settings') }}
                </a>
            </div>
        @else
            <a
                href="{{ $mode === 'company' ? route('admin.departments.index') : route('admin.companies.index') }}"
                class="flex flex-1 flex-col items-center gap-0.5 py-2 text-label-sm {{ request()->routeIs('admin.*') ? 'text-primary' : 'text-on-surface-variant' }}"
            >
                <x-icon name="shield_person" /> {{ __('Admin') }}
            </a>
        @endif
    @endrole
</nav>
