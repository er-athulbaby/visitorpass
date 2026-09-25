<x-sidebar-layout>
    <div class="p-6 max-w-2xl">
        <h1 class="text-xl font-semibold mb-4">{{ __('Departments') }}</h1>

        @if (session('status'))
            <p class="text-green-700 mb-3">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="text-red-600 mb-3" role="alert">{{ session('error') }}</p>
        @endif

        @include('admin.partials.import', ['route' => 'admin.departments', 'columns' => 'Department Name'])

        <form method="POST" action="{{ route('admin.departments.store') }}" class="flex gap-2 mb-6">
            @csrf
            <input type="text" name="name" placeholder="{{ __('Department name') }}" class="border rounded ps-3 pe-3 py-2 flex-1" required>
            <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Add') }}</button>
        </form>

        <ul>
            @foreach ($departments as $department)
                <li class="flex justify-between items-center border-b py-2">
                    <span>{{ $department->name }} ({{ $department->employees_count }})</span>
                    <form method="POST" action="{{ route('admin.departments.destroy', $department) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600">{{ __('Delete') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</x-sidebar-layout>
