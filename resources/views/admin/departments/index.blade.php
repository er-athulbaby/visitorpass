<x-admin-page>
    @include('admin.partials.import', ['route' => 'admin.departments', 'columns' => 'Department Name'])

    <form method="POST" action="{{ route('admin.departments.store') }}" class="flex gap-2 mb-6">
        @csrf
        <input type="text" name="name" placeholder="{{ __('Department name') }}" class="border rounded ps-3 pe-3 py-2 flex-1" required>
        <button type="submit" class="btn btn-primary">{{ __('Add') }}</button>
    </form>

    <ul>
        @foreach ($departments as $department)
            <li class="flex justify-between items-center border-b py-2">
                <span>{{ $department->name }} ({{ $department->employees_count }})</span>
                <div class="flex items-center gap-2">
                <a href="{{ route('admin.departments.edit', $department) }}" class="btn btn-secondary btn-sm"><x-icon name="edit" /> {{ __('Edit') }}</a>
                <form method="POST" action="{{ route('admin.departments.destroy', $department) }}" onsubmit="return confirm({{ Js::from(__('Are you sure you want to delete this?')) }})">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm"><x-icon name="delete" /> {{ __('Delete') }}</button>
                </form>
                </div>
            </li>
        @endforeach
    </ul>
</x-admin-page>
