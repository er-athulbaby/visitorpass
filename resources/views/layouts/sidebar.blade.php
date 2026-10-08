@php
    $brandSettings = \App\Models\Setting::current();
    $brandPrimary = $brandSettings->primary_color ?? '#0F172A';
    $brandOnPrimary = $brandSettings?->onPrimaryColor() ?? '#FFFFFF';
    $brandFaviconUrl = $brandSettings?->favicon_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($brandSettings->favicon_path) : asset('favicon.ico');
    $brandLogoUrl = $brandSettings?->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($brandSettings->logo_path) : null;
    $user = Auth::user();
    $initials = collect(preg_split('/\s+/u', trim($user->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
    $roleLabel = $user->getRoleNames()->first() === 'admin' ? __('Administrator') : __('Receptionist');
    $isArabic = app()->getLocale() === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isArabic ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@isset($title){{ $title }} · @endisset{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="{{ $brandFaviconUrl }}">
        <style>
            :root {
                --color-primary: {{ $brandPrimary }};
                --color-on-primary: {{ $brandOnPrimary }};
            }
        </style>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..24,300..500,0..1,0&display=block" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen md:flex" x-data="{ navOpen: false }" @keydown.escape.window="navOpen = false">
            {{-- Desktop sidebar --}}
            <aside class="hidden w-64 shrink-0 border-e border-outline-variant bg-surface-container-lowest md:block">
                <div class="sticky top-0 flex h-screen flex-col">
                    @include('layouts.partials.sidebar-panel')
                </div>
            </aside>

            {{-- Mobile drawer: the same panel, opened from the top bar --}}
            <div class="md:hidden" x-cloak x-show="navOpen">
                <div class="fixed inset-0 z-40 bg-navy/40" x-show="navOpen" x-transition.opacity.duration.200ms @click="navOpen = false"></div>
                <aside
                    class="fixed inset-y-0 start-0 z-50 flex w-72 max-w-[85vw] flex-col bg-surface-container-lowest shadow-xl"
                    x-show="navOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="ltr:-translate-x-full rtl:translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="ltr:-translate-x-full rtl:translate-x-full"
                    aria-label="{{ __('Menu') }}"
                >
                    @include('layouts.partials.sidebar-panel')
                </aside>
            </div>

            <div class="flex min-h-screen min-w-0 flex-1 flex-col bg-background">
                <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-4 border-b border-outline-variant bg-surface-container-lowest px-4 md:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" class="btn btn-ghost btn-sm -ms-2 md:hidden" @click="navOpen = true" aria-label="{{ __('Open menu') }}">
                            <x-icon name="menu" />
                        </button>
                        <span class="truncate text-label-md font-semibold text-on-surface md:hidden">{{ config('app.name') }}</span>
                        <p class="hidden items-center gap-2 text-label-md text-on-surface-variant md:flex">
                            <x-icon name="calendar_today" class="text-[18px]" />
                            <span class="tabular">{{ now()->translatedFormat('l, j F Y') }}</span>
                        </p>
                    </div>

                    <div class="flex items-center gap-1 sm:gap-2">
                        <form method="POST" action="{{ route('locale.update') }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" name="locale" value="{{ $isArabic ? 'en' : 'ar' }}" class="btn btn-ghost btn-sm" lang="{{ $isArabic ? 'en' : 'ar' }}">
                                <x-icon name="translate" />
                                <span>{{ $isArabic ? 'English' : 'العربية' }}</span>
                            </button>
                        </form>
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 rounded-full p-0.5 transition-colors duration-150 hover:bg-surface-container" aria-label="{{ __('Profile') }}">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-navy text-[13px] font-semibold text-white">{{ $initials }}</span>
                        </a>
                    </div>
                </header>

                @isset($header)
                    <div class="border-b border-outline-variant bg-surface-container-lowest px-4 py-4 md:px-8">
                        {{ $header }}
                    </div>
                @endisset

                <main class="flex-1 pb-24 md:pb-10">
                    <div class="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-8 md:py-8">
                        {{ $slot }}
                    </div>
                </main>

                @include('layouts.sidebar-nav', ['variant' => 'bottom'])
            </div>
        </div>
    </body>
</html>
