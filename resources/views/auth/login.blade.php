<x-guest-layout>
    <h1 class="text-headline-sm text-on-surface">{{ __('Sign in') }}</h1>
    <p class="mt-1 text-body-sm text-on-surface-variant">{{ __('Use the account your administrator gave you.') }}</p>

    <x-auth-session-status class="mt-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" class="!mb-0" />
                @if (Route::has('password.request'))
                    <a class="text-[13px] font-medium text-primary hover:underline" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" class="mt-1.5" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <label for="remember_me" class="flex items-center gap-2">
            <input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-outline text-primary focus:ring-primary" name="remember">
            <span class="text-body-sm text-on-surface-variant">{{ __('Remember me') }}</span>
        </label>

        <x-primary-button class="btn-lg w-full">
            {{ __('Log in') }}
        </x-primary-button>
    </form>
</x-guest-layout>
