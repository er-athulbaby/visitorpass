<x-guest-layout>
    <form method="POST" action="{{ route('setup.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ request('token') }}">

        <div>
            <span class="badge badge-info">{{ __('Step 2 of 2') }}</span>
            <h1 class="mt-3 text-headline-sm text-on-surface">{{ __('Welcome — let\'s set up VisitorPass') }}</h1>
        </div>

        <fieldset>
            <legend class="field-label">{{ __('What kind of reception desk is this for?') }}</legend>

            <div class="grid gap-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-outline-variant p-3 transition-colors duration-150 hover:bg-surface-container-low has-[:checked]:border-primary has-[:checked]:bg-[var(--color-primary-soft)]">
                    <input type="radio" name="deployment_mode" value="company" checked class="mt-0.5 text-primary focus:ring-primary">
                    <span>
                        <span class="flex items-center gap-2 font-semibold text-on-surface"><x-icon name="badge" class="text-[18px]" /> {{ __('Company') }}</span>
                        <span class="mt-0.5 block text-[13px] text-on-surface-variant">{{ __('A company (visitors check in to see a specific person)') }}</span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-outline-variant p-3 transition-colors duration-150 hover:bg-surface-container-low has-[:checked]:border-primary has-[:checked]:bg-[var(--color-primary-soft)]">
                    <input type="radio" name="deployment_mode" value="building" class="mt-0.5 text-primary focus:ring-primary">
                    <span>
                        <span class="flex items-center gap-2 font-semibold text-on-surface"><x-icon name="domain" class="text-[18px]" /> {{ __('Building') }}</span>
                        <span class="mt-0.5 block text-[13px] text-on-surface-variant">{{ __('A building (visitors check in to see a company)') }}</span>
                    </span>
                </label>
            </div>

            @error('deployment_mode')
                <p class="field-error" role="alert">{{ $message }}</p>
            @enderror
        </fieldset>

        <div>
            <x-input-label for="admin_name" :value="__('Your name')" />
            <x-text-input id="admin_name" name="admin_name" type="text" :value="old('admin_name')" required />
            <x-input-error :messages="$errors->get('admin_name')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="admin_email" :value="__('Your email')" />
            <x-text-input id="admin_email" name="admin_email" type="email" :value="old('admin_email')" required />
            <x-input-error :messages="$errors->get('admin_email')" class="mt-1.5" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="admin_password" :value="__('Password')" />
                <x-text-input id="admin_password" name="admin_password" type="password" autocomplete="new-password" required />
                <x-input-error :messages="$errors->get('admin_password')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label for="admin_password_confirmation" :value="__('Confirm password')" />
                <x-text-input id="admin_password_confirmation" name="admin_password_confirmation" type="password" autocomplete="new-password" required />
            </div>
        </div>

        <x-primary-button class="btn-lg w-full">{{ __('Finish setup') }}</x-primary-button>
    </form>
</x-guest-layout>
