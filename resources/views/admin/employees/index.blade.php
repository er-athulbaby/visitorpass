<x-admin-page>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <section class="card" aria-labelledby="add-heading">
                <div class="card-header"><h2 id="add-heading" class="card-title">{{ __('Add employee') }}</h2></div>
                <form method="POST" action="{{ route('admin.employees.store') }}" class="card-body space-y-4">
                    @csrf
                    <div>
                        <label for="new-employee-department" class="field-label">{{ __('Department') }}</label>
                        <select id="new-employee-department" name="department_id" class="field" required>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="new-employee-name" class="field-label">{{ __('Employee name') }}</label>
                        <input id="new-employee-name" type="text" name="name" class="field" required>
                    </div>
                    <div>
                        <label for="new-employee-email" class="field-label">{{ __('Email (optional)') }}</label>
                        <input id="new-employee-email" type="email" name="email" class="field">
                        <p class="field-help">{{ __('Used to email the host when their visitor checks in.') }}</p>
                    </div>
                    <button type="submit" class="btn btn-primary w-full"><x-icon name="add" /> {{ __('Add') }}</button>
                </form>
            </section>

            @include('admin.partials.import', ['route' => 'admin.employees', 'columns' => 'Employee Name, Department, Email (optional)'])
        </div>

        <section class="card lg:col-span-2" aria-labelledby="list-heading">
            <div class="card-header">
                <h2 id="list-heading" class="card-title">{{ __('Employees') }}</h2>
                <span class="badge badge-neutral tabular">{{ $employees->count() }}</span>
            </div>
            @if ($employees->isEmpty())
                <x-empty-state icon="badge" :title="__('No employees yet')" :text="__('Add the people visitors come to see, or import them from a CSV file.')" />
            @else
                <div class="overflow-x-auto">
                    <table class="data-table min-w-[560px]">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Department') }}</th>
                                <th>{{ __('Email') }}</th>
                                <th class="text-end"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employees as $employee)
                                <tr>
                                    <td class="font-semibold text-on-surface">{{ $employee->name }}</td>
                                    <td>{{ $employee->department->name }}</td>
                                    <td>{{ $employee->email ?? '—' }}</td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-secondary btn-sm"><x-icon name="edit" /> {{ __('Edit') }}</a>
                                            <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}" onsubmit="return confirm({{ Js::from(__('Are you sure you want to delete this?')) }})">
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
