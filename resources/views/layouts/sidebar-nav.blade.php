@php
    $mode = \App\Models\Setting::current()?->deployment_mode;
    $isSidebar = ($variant ?? 'sidebar') === 'sidebar';
    $adminHome = $mode === 'company' ? route('admin.departments.index') : route('admin.companies.index');
    $sections = [
        __('Overview') => [
            ['route' => 'dashboard', 'label' => __('Home'), 'icon' => 'space_dashboard'],
        ],
        __('Visitor management') => [
            ['route' => 'visits.create', 'label' => __('Scan'), 'icon' => 'id_card'],
            ['route' => 'visits.index', 'label' => __('Visitors'), 'icon' => 'groups'],
            ['route' => 'history.index', 'label' => __('History'), 'icon' => 'history'],
        ],
    ];
@endphp

@if ($isSidebar)
    <nav class="space-y-6" aria-label="{{ __('Main') }}">
        @foreach ($sections as $heading => $items)
            <div>
                <p class="mb-1.5 px-3 text-[12px] font-medium text-on-surface-variant">{{ $heading }}</p>
                <div class="space-y-0.5">
                    @foreach ($items as $item)
                        <a href="{{ route($item['route']) }}" class="nav-item {{ request()->routeIs($item['route']) ? 'nav-item-active' : '' }}" @if (request()->routeIs($item['route'])) aria-current="page" @endif>
                            <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach

        @role('admin')
            <div>
                <p class="mb-1.5 px-3 text-[12px] font-medium text-on-surface-variant">{{ __('System') }}</p>
                <a href="{{ $adminHome }}" class="nav-item {{ request()->routeIs('admin.*') ? 'nav-item-active' : '' }}" @if (request()->routeIs('admin.*')) aria-current="page" @endif>
                    <x-icon name="shield_person" /> {{ __('Admin') }}
                </a>
            </div>
        @endrole
    </nav>
@else
    <nav class="fixed inset-x-0 bottom-0 flex border-t border-outline-variant bg-surface-container-lowest md:hidden z-30 pb-[env(safe-area-inset-bottom)]" aria-label="{{ __('Main') }}">
        @foreach (collect($sections)->flatten(1) as $item)
            <a href="{{ route($item['route']) }}" class="flex min-h-[56px] flex-1 flex-col items-center justify-center gap-0.5 text-[11px] font-medium transition-colors duration-150 {{ request()->routeIs($item['route']) ? 'text-primary' : 'text-on-surface-variant' }}" @if (request()->routeIs($item['route'])) aria-current="page" @endif>
                <x-icon :name="$item['icon']" /> {{ $item['label'] }}
            </a>
        @endforeach
        @role('admin')
            <a href="{{ $adminHome }}" class="flex min-h-[56px] flex-1 flex-col items-center justify-center gap-0.5 text-[11px] font-medium transition-colors duration-150 {{ request()->routeIs('admin.*') ? 'text-primary' : 'text-on-surface-variant' }}">
                <x-icon name="shield_person" /> {{ __('Admin') }}
            </a>
        @endrole
    </nav>
@endif
