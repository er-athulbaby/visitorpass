<x-admin-page>
    <form method="POST" action="{{ route('admin.employees.update', $employee) }}" class="max-w-xl space-y-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        @csrf
        @method('PUT')
        <h2 class="text-headline-sm text-on-surface">{{ __('Edit employee') }}</h2>

        <div>
            <label for="name" class="text-label-sm text-on-surface-variant">{{ __('Employee name') }}</label>
            <input id="name" name="name" type="text" value="{{ old('name', $employee->name) }}" class="border rounded ps-3 pe-3 py-2 block w-full" required>
            @error('name') <p class="text-red-600 text-sm" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="department_id" class="text-label-sm text-on-surface-variant">{{ __('Department') }}</label>
            <select id="department_id" name="department_id" class="border rounded ps-3 pe-10 py-2 block w-full" required>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id', $employee->department_id) == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('department_id') <p class="text-red-600 text-sm" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="text-label-sm text-on-surface-variant">{{ __('Email (optional)') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email', $employee->email) }}" class="border rounded ps-3 pe-3 py-2 block w-full">
            @error('email') <p class="text-red-600 text-sm" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary"><x-icon name="save" /> {{ __('Save') }}</button>
            <a href="{{ route('admin.employees.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-admin-page>
