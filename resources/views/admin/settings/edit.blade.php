<x-app-layout>
    <div class="p-6 max-w-xl">
        <h1 class="text-xl font-semibold mb-4">{{ __('Settings') }}</h1>

        @if (session('status'))
            <p class="text-green-700 mb-3">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PATCH')

            <div class="mb-4">
                <label for="primary_color">{{ __('Primary Color') }}</label>
                <input id="primary_color" name="primary_color" type="color" value="{{ old('primary_color', $setting->primary_color) }}" class="block h-10 w-20 border rounded">
                @error('primary_color')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="tagline">{{ __('Tagline') }}</label>
                <input id="tagline" name="tagline" type="text" value="{{ old('tagline', $setting->tagline) }}" class="border rounded ps-3 pe-3 py-2 block w-full">
                @error('tagline')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="logo">{{ __('Logo') }}</label>
                <input id="logo" name="logo" type="file" accept="image/*" class="block">
                @error('logo')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="favicon">{{ __('Favicon') }}</label>
                <input id="favicon" name="favicon" type="file" accept="image/*" class="block">
                @error('favicon')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <h2 class="text-lg font-semibold mt-6 mb-2">{{ __('Email Notifications') }}</h2>

            <div class="mb-4">
                <label for="smtp_host">{{ __('SMTP Host') }}</label>
                <input id="smtp_host" name="smtp_host" type="text" value="{{ old('smtp_host', $setting->smtp_host) }}" class="border rounded ps-3 pe-3 py-2 block w-full">
                @error('smtp_host')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="smtp_port">{{ __('SMTP Port') }}</label>
                <input id="smtp_port" name="smtp_port" type="text" value="{{ old('smtp_port', $setting->smtp_port) }}" class="border rounded ps-3 pe-3 py-2 block w-full">
                @error('smtp_port')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="smtp_username">{{ __('SMTP Username') }}</label>
                <input id="smtp_username" name="smtp_username" type="text" value="{{ old('smtp_username', $setting->smtp_username) }}" class="border rounded ps-3 pe-3 py-2 block w-full">
                @error('smtp_username')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="smtp_password">{{ __('SMTP Password') }}</label>
                <input id="smtp_password" name="smtp_password" type="password" value="" class="border rounded ps-3 pe-3 py-2 block w-full">
                <p class="text-gray-500 text-sm">{{ __('Leave blank to keep the current password.') }}</p>
                @error('smtp_password')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="smtp_from_address">{{ __('From Address') }}</label>
                <input id="smtp_from_address" name="smtp_from_address" type="text" value="{{ old('smtp_from_address', $setting->smtp_from_address) }}" class="border rounded ps-3 pe-3 py-2 block w-full">
                @error('smtp_from_address')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Save') }}</button>
        </form>

        <form method="POST" action="{{ route('admin.settings.test-email') }}" class="mt-2">
            @csrf
            @if (session('error'))
                <p class="text-red-600 mb-2">{{ session('error') }}</p>
            @endif
            <button type="submit" class="bg-gray-200 text-gray-800 rounded px-4 py-2">{{ __('Send test email') }}</button>
        </form>
    </div>
</x-app-layout>
