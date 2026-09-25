<x-sidebar-layout>
    <div class="p-6 max-w-2xl">
        <h1 class="text-xl font-semibold mb-4">{{ __('Employees') }}</h1>

        @if (session('status'))
            <p class="text-green-700 mb-3">{{ session('status') }}</p>
        @endif

        @include('admin.partials.import', ['route' => 'admin.employees', 'columns' => 'Employee Name, Department, Email (optional)'])

        <form method="POST" action="{{ route('admin.employees.store') }}" class="flex gap-2 mb-6">
            @csrf
            <select name="department_id" class="border rounded ps-3 pe-3 py-2" required>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </select>
            <input type="text" name="name" placeholder="{{ __('Employee name') }}" class="border rounded ps-3 pe-3 py-2 flex-1" required>
            <input type="email" name="email" placeholder="{{ __('Email (optional)') }}" class="border rounded ps-3 pe-3 py-2 flex-1">
            <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Add') }}</button>
        </form>

        <ul>
            @foreach ($employees as $employee)
                <li class="flex justify-between items-center border-b py-2">
                    <span>{{ $employee->name }} — {{ $employee->department->name }}</span>
                    <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600">{{ __('Delete') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</x-sidebar-layout>
