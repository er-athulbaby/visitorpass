<x-admin-page>
    <form method="POST" action="{{ route('admin.departments.update', $department) }}" class="max-w-xl space-y-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        @csrf
        @method('PUT')
        <h2 class="text-headline-sm text-on-surface">{{ __('Edit department') }}</h2>

        <div>
            <label for="name" class="text-label-sm text-on-surface-variant">{{ __('Department name') }}</label>
            <input id="name" name="name" type="text" value="{{ old('name', $department->name) }}" class="border rounded ps-3 pe-3 py-2 block w-full" required>
            @error('name') <p class="text-red-600 text-sm" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary"><x-icon name="save" /> {{ __('Save') }}</button>
            <a href="{{ route('admin.departments.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-admin-page>
