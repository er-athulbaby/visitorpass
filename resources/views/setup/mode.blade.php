<x-guest-layout>
    <form method="POST" action="{{ route('setup.store') }}">
        @csrf

        <h1 class="text-xl font-semibold mb-4">{{ __('Welcome — let\'s set up VisitorPass') }}</h1>

        <fieldset class="mb-4">
            <legend class="mb-2">{{ __('What kind of reception desk is this for?') }}</legend>

            <label class="flex items-center gap-2 mb-2">
                <input type="radio" name="deployment_mode" value="company" checked>
                {{ __('A company (visitors check in to see a specific person)') }}
            </label>

            <label class="flex items-center gap-2">
                <input type="radio" name="deployment_mode" value="building">
                {{ __('A building (visitors check in to see a company)') }}
            </label>

            @error('deployment_mode')
                <p class="text-red-600 text-sm mt-1" role="alert">{{ $message }}</p>
            @enderror
        </fieldset>

        <div class="mb-3">
            <x-input-label for="admin_name" :value="__('Your name')" />
            <x-text-input id="admin_name" name="admin_name" type="text" class="mt-1 block w-full" :value="old('admin_name')" required />
            <x-input-error :messages="$errors->get('admin_name')" class="mt-1" />
        </div>

        <div class="mb-3">
            <x-input-label for="admin_email" :value="__('Your email')" />
            <x-text-input id="admin_email" name="admin_email" type="email" class="mt-1 block w-full" :value="old('admin_email')" required />
            <x-input-error :messages="$errors->get('admin_email')" class="mt-1" />
        </div>

        <div class="mb-3">
            <x-input-label for="admin_password" :value="__('Password')" />
            <x-text-input id="admin_password" name="admin_password" type="password" class="mt-1 block w-full" required />
            <x-input-error :messages="$errors->get('admin_password')" class="mt-1" />
        </div>

        <div class="mb-4">
            <x-input-label for="admin_password_confirmation" :value="__('Confirm password')" />
            <x-text-input id="admin_password_confirmation" name="admin_password_confirmation" type="password" class="mt-1 block w-full" required />
        </div>

        <x-primary-button>{{ __('Finish setup') }}</x-primary-button>
    </form>
</x-guest-layout>
