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

            <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Save') }}</button>
        </form>
    </div>
</x-app-layout>
