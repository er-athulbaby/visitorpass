<x-admin-page>
    <form method="POST" action="{{ route('admin.users.store') }}" class="flex gap-2 mb-6 flex-wrap">
        @csrf
        <input type="text" name="name" placeholder="{{ __('Name') }}" class="border rounded ps-3 pe-3 py-2" required>
        <input type="email" name="email" placeholder="{{ __('Email') }}" class="border rounded ps-3 pe-3 py-2" required>
        <input type="password" name="password" placeholder="{{ __('Password') }}" class="border rounded ps-3 pe-3 py-2" required>
        <select name="role" class="border rounded ps-3 pe-10 py-2" required>
            <option value="receptionist">{{ __('Receptionist') }}</option>
            <option value="admin">{{ __('Admin') }}</option>
        </select>
        <button type="submit" class="btn btn-primary">{{ __('Add') }}</button>
    </form>

    <ul>
        @foreach ($users as $user)
            <li class="flex justify-between items-center border-b py-2">
                <span>{{ $user->name }} ({{ $user->roles->pluck('name')->map(fn ($role) => $role === 'admin' ? __('Administrator') : __('Receptionist'))->join(', ') }})</span>
                <div class="flex items-center gap-2">
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-secondary btn-sm"><x-icon name="edit" /> {{ __('Edit') }}</a>
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm({{ Js::from(__('Are you sure you want to delete this?')) }})">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm"><x-icon name="delete" /> {{ __('Remove') }}</button>
                </form>
                </div>
            </li>
        @endforeach
    </ul>
</x-admin-page>
