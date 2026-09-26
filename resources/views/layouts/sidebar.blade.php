@php
    $brandSettings = \App\Models\Setting::current();
    $brandPrimary = $brandSettings->primary_color ?? '#0F172A';
    $brandOnPrimary = $brandSettings?->onPrimaryColor() ?? '#FFFFFF';
    $brandFaviconUrl = $brandSettings?->favicon_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($brandSettings->favicon_path) : asset('favicon.ico');
    $brandLogoUrl = $brandSettings?->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($brandSettings->logo_path) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
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
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-background md:flex">
            <aside class="hidden w-64 shrink-0 flex-col border-e border-outline-variant bg-surface-container-lowest md:flex">
                <div class="flex items-center justify-center gap-2 border-b border-outline-variant px-4 py-5">
                    @if ($brandLogoUrl)
                        <img src="{{ $brandLogoUrl }}" alt="{{ config('app.name') }}" class="max-h-28 w-full object-contain">
                    @else
                        <x-application-logo class="h-20 w-auto fill-current text-on-surface" />
                    @endif
                </div>

                @include('layouts.sidebar-nav', ['variant' => 'sidebar'])

                <div class="border-t border-outline-variant p-4">
                    <a href="{{ route('profile.edit') }}" class="block hover:underline">
                        <p class="text-label-md text-on-surface">{{ Auth::user()->name }}</p>
                        <p class="text-label-sm text-on-surface-variant">{{ Auth::user()->getRoleNames()->first() === 'admin' ? __('Administrator') : __('Receptionist') }}</p>
                    </a>
                    <form method="POST" action="{{ route('locale.update') }}" class="mt-3">
                        @csrf
                        @method('PATCH')
                        <button type="submit" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}" class="flex items-center gap-2 text-label-md text-on-surface-variant">
                            <x-icon name="language" /> {{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="flex items-center gap-2 text-label-md text-error">
                            <x-icon name="logout" /> {{ __('Sign out') }}
                        </button>
                    </form>
                    <p class="mt-4 text-label-sm text-on-surface-variant">
                        v1.0 &middot; {{ __('Developed by') }}
                        <a href="https://deverra.me" target="_blank" rel="noopener" class="hover:underline">DeVerra Technologies</a>
                    </p>
                </div>
            </aside>

            <div class="flex min-h-screen flex-1 flex-col">
                <header class="flex items-center justify-between border-b border-outline-variant bg-surface-container-lowest px-4 py-3 md:hidden">
                    <span class="text-headline-md-mobile text-primary">{{ config('app.name') }}</span>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('profile.edit') }}" aria-label="{{ __('Profile') }}" class="text-on-surface-variant">
                            <x-icon name="person" />
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" aria-label="{{ __('Sign out') }}" class="text-on-surface-variant">
                                <x-icon name="logout" />
                            </button>
                        </form>
                    </div>
                </header>

                @isset($header)
                    <div class="border-b border-outline-variant bg-surface-container-lowest px-4 py-4 md:px-8">
                        {{ $header }}
                    </div>
                @endisset

                <main class="flex-1 pb-20 md:pb-0">
                    {{ $slot }}
                </main>

                @include('layouts.sidebar-nav', ['variant' => 'bottom'])
            </div>
        </div>
    </body>
</html>
