@php
    $brandSettings = \App\Models\Setting::current();
    $brandPrimary = $brandSettings->primary_color ?? '#0F172A';
    $brandOnPrimary = $brandSettings?->onPrimaryColor() ?? '#FFFFFF';
    $brandFaviconUrl = $brandSettings?->favicon_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($brandSettings->favicon_path) : asset('favicon.ico');
    $brandLogoUrl = $brandSettings?->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($brandSettings->logo_path) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="{{ $brandFaviconUrl }}">
        <style>
            :root {
                --color-primary: {{ $brandPrimary }};
                --color-on-primary: {{ $brandOnPrimary }};
            }
        </style>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..24,300..500,0..1,0&display=block" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="flex min-h-screen flex-col bg-background">
            <main class="flex flex-1 flex-col items-center justify-center px-4 py-10">
                <a href="{{ url('/') }}" class="flex flex-col items-center">
                    @if ($brandLogoUrl)
                        <img src="{{ $brandLogoUrl }}" alt="{{ config('app.name') }}" class="h-24 w-auto max-w-xs object-contain">
                    @else
                        <x-application-logo class="h-16 w-16 fill-current text-on-surface" />
                    @endif
                </a>
                @if ($brandSettings?->tagline)
                    <p class="mt-3 text-center text-body-lg font-semibold text-on-surface">{{ $brandSettings->tagline }}</p>
                @endif

                <div class="card mt-8 w-full max-w-md p-6 sm:p-8">
                    {{ $slot }}
                </div>
            </main>

            <footer class="px-4 pb-6 text-center text-[12px] text-on-surface-variant">
                v1.0 &middot; {{ __('Developed by') }}
                <a href="https://deverra.me" target="_blank" rel="noopener" class="font-medium hover:text-on-surface hover:underline">DeVerra Technologies</a>
            </footer>
        </div>
    </body>
</html>
