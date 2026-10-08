<x-admin-page>
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PATCH')

        <section class="card" aria-labelledby="branding-heading">
            <div class="card-header">
                <div>
                    <h2 id="branding-heading" class="card-title">{{ __('Branding') }}</h2>
                    <p class="mt-0.5 text-[13px] text-on-surface-variant">{{ __('How this installation looks to your staff.') }}</p>
                </div>
            </div>
            <div class="card-body grid gap-5 md:grid-cols-2">
                <div>
                    <label for="primary_color" class="field-label">{{ __('Primary Color') }}</label>
                    <input id="primary_color" name="primary_color" type="color" value="{{ old('primary_color', $setting->primary_color) }}" class="block h-11 w-20 cursor-pointer rounded-lg border border-outline-variant bg-surface-container-lowest p-1">
                    <p class="field-help">{{ __('Used for buttons, links and the active menu item.') }}</p>
                    @error('primary_color')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="tagline" class="field-label">{{ __('Tagline') }}</label>
                    <input id="tagline" name="tagline" type="text" value="{{ old('tagline', $setting->tagline) }}" class="field">
                    <p class="field-help">{{ __('Shown under the logo on the sign-in page.') }}</p>
                    @error('tagline')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="logo" class="field-label">{{ __('Logo') }}</label>
                    @if ($setting->logo_path)
                        <div class="mb-2 flex h-16 items-center rounded-lg border border-outline-variant bg-surface-container-low px-3">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($setting->logo_path) }}" alt="{{ __('Current logo') }}" class="max-h-12 w-auto object-contain">
                        </div>
                    @endif
                    <input id="logo" name="logo" type="file" accept="image/*" class="file-input">
                    <p class="field-help">{{ __('PNG, JPG or WebP. A wide logo at least 640px across looks sharpest.') }}</p>
                    @error('logo')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="favicon" class="field-label">{{ __('Favicon') }}</label>
                    <input id="favicon" name="favicon" type="file" accept="image/*" class="file-input">
                    <p class="field-help">{{ __('The small icon in the browser tab.') }}</p>
                    @error('favicon')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="email-heading">
            <div class="card-header">
                <div>
                    <h2 id="email-heading" class="card-title">{{ __('Email Notifications') }}</h2>
                    <p class="mt-0.5 text-[13px] text-on-surface-variant">{{ __('Hosts get an email when their visitor checks in.') }}</p>
                </div>
            </div>
            <div class="card-body grid gap-5 md:grid-cols-2">
                <div>
                    <label for="smtp_host" class="field-label">{{ __('SMTP Host') }}</label>
                    <input id="smtp_host" name="smtp_host" type="text" value="{{ old('smtp_host', $setting->smtp_host) }}" class="field" placeholder="smtp.gmail.com">
                    @error('smtp_host')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="smtp_port" class="field-label">{{ __('SMTP Port') }}</label>
                    <input id="smtp_port" name="smtp_port" type="text" inputmode="numeric" value="{{ old('smtp_port', $setting->smtp_port) }}" class="field tabular" placeholder="587">
                    @error('smtp_port')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="smtp_username" class="field-label">{{ __('SMTP Username') }}</label>
                    <input id="smtp_username" name="smtp_username" type="text" value="{{ old('smtp_username', $setting->smtp_username) }}" class="field" autocomplete="off">
                    @error('smtp_username')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="smtp_password" class="field-label">{{ __('SMTP Password') }}</label>
                    <input id="smtp_password" name="smtp_password" type="password" value="" class="field" autocomplete="new-password">
                    <p class="field-help">{{ __('Leave blank to keep the current password.') }}</p>
                    @error('smtp_password')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="smtp_from_address" class="field-label">{{ __('From Address') }}</label>
                    <input id="smtp_from_address" name="smtp_from_address" type="text" value="{{ old('smtp_from_address', $setting->smtp_from_address) }}" class="field md:max-w-md">
                    @error('smtp_from_address')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div class="flex justify-end border-t border-outline-variant px-5 py-4">
                <button type="submit" class="btn btn-primary"><x-icon name="save" /> {{ __('Save') }}</button>
            </div>
        </section>
    </form>

    <section class="card mt-6" aria-labelledby="test-email-heading">
        <form method="POST" action="{{ route('admin.settings.test-email') }}" class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
            @csrf
            <div>
                <h2 id="test-email-heading" class="text-label-md font-semibold text-on-surface">{{ __('Check email delivery') }}</h2>
                <p class="mt-0.5 text-[13px] text-on-surface-variant">{{ __('Sends a test message to the From Address using the saved settings.') }}</p>
            </div>
            <button type="submit" class="btn btn-secondary"><x-icon name="send" class="icon-directional" /> {{ __('Send test email') }}</button>
        </form>
    </section>
</x-admin-page>
