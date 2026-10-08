<x-admin-page>
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card lg:self-start" aria-labelledby="add-heading">
            <div class="card-header"><h2 id="add-heading" class="card-title">{{ __('Add user') }}</h2></div>
            <form method="POST" action="{{ route('admin.users.store') }}" class="card-body space-y-4">
                @csrf
                <div>
                    <label for="new-user-name" class="field-label">{{ __('Name') }}</label>
                    <input id="new-user-name" type="text" name="name" class="field" required>
                </div>
                <div>
                    <label for="new-user-email" class="field-label">{{ __('Email') }}</label>
                    <input id="new-user-email" type="email" name="email" class="field" required>
                </div>
                <div>
                    <label for="new-user-password" class="field-label">{{ __('Password') }}</label>
                    <input id="new-user-password" type="password" name="password" autocomplete="new-password" class="field" required>
                    <p class="field-help">{{ __('At least 8 characters.') }}</p>
                </div>
                <div>
                    <label for="new-user-role" class="field-label">{{ __('Role') }}</label>
                    <select id="new-user-role" name="role" class="field" required>
                        <option value="receptionist">{{ __('Receptionist') }}</option>
                        <option value="admin">{{ __('Admin') }}</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-full"><x-icon name="add" /> {{ __('Add') }}</button>
            </form>
        </section>

        <section class="card lg:col-span-2" aria-labelledby="list-heading">
            <div class="card-header">
                <h2 id="list-heading" class="card-title">{{ __('Users') }}</h2>
                <span class="badge badge-neutral tabular">{{ $users->count() }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table min-w-[560px]">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Role') }}</th>
                            <th class="text-end"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="font-semibold text-on-surface">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @foreach ($user->roles->pluck('name') as $role)
                                        <span class="badge {{ $role === 'admin' ? 'badge-info' : 'badge-neutral' }}">{{ $role === 'admin' ? __('Administrator') : __('Receptionist') }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-secondary btn-sm"><x-icon name="edit" /> {{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm({{ Js::from(__('Are you sure you want to delete this?')) }})">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm"><x-icon name="delete" /> {{ __('Remove') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-admin-page>
