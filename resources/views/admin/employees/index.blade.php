<x-admin-page>
    @include('admin.partials.import', ['route' => 'admin.employees', 'columns' => 'Employee Name, Department, Email (optional)'])

    <form method="POST" action="{{ route('admin.employees.store') }}" class="flex gap-2 mb-6">
        @csrf
        <select name="department_id" class="border rounded ps-3 pe-10 py-2" required>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}">{{ $department->name }}</option>
            @endforeach
        </select>
        <input type="text" name="name" placeholder="{{ __('Employee name') }}" class="border rounded ps-3 pe-3 py-2 flex-1" required>
        <input type="email" name="email" placeholder="{{ __('Email (optional)') }}" class="border rounded ps-3 pe-3 py-2 flex-1">
        <button type="submit" class="btn btn-primary">{{ __('Add') }}</button>
    </form>

    <ul>
        @foreach ($employees as $employee)
            <li class="flex justify-between items-center border-b py-2">
                <span>{{ $employee->name }} — {{ $employee->department->name }}</span>
                <div class="flex items-center gap-2">
                <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-secondary btn-sm"><x-icon name="edit" /> {{ __('Edit') }}</a>
                <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}" onsubmit="return confirm({{ Js::from(__('Are you sure you want to delete this?')) }})">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm"><x-icon name="delete" /> {{ __('Delete') }}</button>
                </form>
                </div>
            </li>
        @endforeach
    </ul>
</x-admin-page>
