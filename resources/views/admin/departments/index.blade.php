<x-admin-page>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <section class="card" aria-labelledby="add-heading">
                <div class="card-header"><h2 id="add-heading" class="card-title">{{ __('Add department') }}</h2></div>
                <form method="POST" action="{{ route('admin.departments.store') }}" class="card-body space-y-4">
                    @csrf
                    <div>
                        <label for="new-department-name" class="field-label">{{ __('Department name') }}</label>
                        <input id="new-department-name" type="text" name="name" class="field" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-full"><x-icon name="add" /> {{ __('Add') }}</button>
                </form>
            </section>

            @include('admin.partials.import', ['route' => 'admin.departments', 'columns' => 'Department Name'])
        </div>

        <section class="card lg:col-span-2" aria-labelledby="list-heading">
            <div class="card-header">
                <h2 id="list-heading" class="card-title">{{ __('Departments') }}</h2>
                <span class="badge badge-neutral tabular">{{ $departments->count() }}</span>
            </div>
            @if ($departments->isEmpty())
                <x-empty-state icon="apartment" :title="__('No departments yet')" :text="__('Add a department or import a list to get started.')" />
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Employees') }}</th>
                                <th class="text-end"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($departments as $department)
                                <tr>
                                    <td class="font-semibold text-on-surface">{{ $department->name }}</td>
                                    <td class="tabular">{{ $department->employees_count }}</td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.departments.edit', $department) }}" class="btn btn-secondary btn-sm"><x-icon name="edit" /> {{ __('Edit') }}</a>
                                            <form method="POST" action="{{ route('admin.departments.destroy', $department) }}" onsubmit="return confirm({{ Js::from(__('Are you sure you want to delete this?')) }})">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm"><x-icon name="delete" /> {{ __('Delete') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-admin-page>
