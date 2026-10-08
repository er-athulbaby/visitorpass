<x-admin-page>
    <a href="{{ route('admin.employees.index') }}" class="btn btn-ghost btn-sm -ms-3 mb-3"><x-icon name="arrow_back" class="icon-directional" /> {{ __('Back') }}</a>

    <form method="POST" action="{{ route('admin.employees.update', $employee) }}" class="card max-w-2xl">
        @csrf
        @method('PUT')
        <div class="card-header"><h2 class="card-title">{{ __('Edit employee') }}</h2></div>
        <div class="card-body space-y-5">

        <div>
            <label for="name" class="field-label">{{ __('Employee name') }}</label>
            <input id="name" name="name" type="text" value="{{ old('name', $employee->name) }}" class="field" required>
            @error('name') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="department_id" class="field-label">{{ __('Department') }}</label>
            <select id="department_id" name="department_id" class="field" required>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id', $employee->department_id) == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('department_id') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="field-label">{{ __('Email (optional)') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email', $employee->email) }}" class="field">
            @error('email') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>

        </div>
        <div class="flex justify-end gap-2 border-t border-outline-variant px-5 py-4">
            <a href="{{ route('admin.employees.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary"><x-icon name="save" /> {{ __('Save') }}</button>
        </div>
    </form>
</x-admin-page>
