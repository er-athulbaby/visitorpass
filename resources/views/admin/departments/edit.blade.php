<x-admin-page>
    <a href="{{ route('admin.departments.index') }}" class="btn btn-ghost btn-sm -ms-3 mb-3"><x-icon name="arrow_back" class="icon-directional" /> {{ __('Back') }}</a>

    <form method="POST" action="{{ route('admin.departments.update', $department) }}" class="card max-w-2xl">
        @csrf
        @method('PUT')
        <div class="card-header"><h2 class="card-title">{{ __('Edit department') }}</h2></div>
        <div class="card-body space-y-5">

        <div>
            <label for="name" class="field-label">{{ __('Department name') }}</label>
            <input id="name" name="name" type="text" value="{{ old('name', $department->name) }}" class="field" required>
            @error('name') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>

        </div>
        <div class="flex justify-end gap-2 border-t border-outline-variant px-5 py-4">
            <a href="{{ route('admin.departments.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary"><x-icon name="save" /> {{ __('Save') }}</button>
        </div>
    </form>
</x-admin-page>
