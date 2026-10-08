{{-- Shared by the desktop sidebar and the mobile drawer (expects $brandLogoUrl, $user, $initials, $roleLabel, $isArabic). --}}
<div class="flex h-20 shrink-0 items-center border-b border-outline-variant px-5">
    <a href="{{ route('dashboard') }}" class="flex w-full items-center justify-center">
        @if ($brandLogoUrl)
            <img src="{{ $brandLogoUrl }}" alt="{{ config('app.name') }}" class="max-h-12 w-auto max-w-full object-contain">
        @else
            <x-application-logo class="h-10 w-auto fill-current text-on-surface" />
        @endif
    </a>
</div>

<div class="flex-1 overflow-y-auto px-3 py-4">
    @include('layouts.sidebar-nav', ['variant' => 'sidebar'])
</div>

<div class="shrink-0 border-t border-outline-variant p-3">
    <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg p-2 transition-colors duration-150 hover:bg-surface-container">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy text-[13px] font-semibold text-white">{{ $initials }}</span>
        <span class="min-w-0">
            <span class="block truncate text-label-md font-semibold text-on-surface">{{ $user->name }}</span>
            <span class="block truncate text-label-sm text-on-surface-variant">{{ $roleLabel }}</span>
        </span>
    </a>

    <form method="POST" action="{{ route('logout') }}" class="mt-1">
        @csrf
        <button type="submit" class="nav-item w-full hover:!bg-error-container hover:!text-error">
            <x-icon name="logout" class="icon-directional" /> {{ __('Sign out') }}
        </button>
    </form>

    <p class="mt-3 px-2 text-[11px] leading-4 text-on-surface-variant">
        v1.0 &middot; {{ __('Developed by') }}
        <a href="https://deverra.me" target="_blank" rel="noopener" class="font-medium hover:text-on-surface hover:underline">DeVerra Technologies</a>
    </p>
</div>
