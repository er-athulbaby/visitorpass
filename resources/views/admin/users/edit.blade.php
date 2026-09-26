<x-admin-page>
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="max-w-xl space-y-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        @csrf
        @method('PUT')
        <h2 class="text-headline-sm text-on-surface">{{ __('Edit user') }}</h2>

        <div>
            <label for="name" class="text-label-sm text-on-surface-variant">{{ __('Name') }}</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" class="border rounded ps-3 pe-3 py-2 block w-full" required>
            @error('name') <p class="text-red-600 text-sm" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="text-label-sm text-on-surface-variant">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" class="border rounded ps-3 pe-3 py-2 block w-full" required>
            @error('email') <p class="text-red-600 text-sm" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="role" class="text-label-sm text-on-surface-variant">{{ __('Role') }}</label>
            @php($currentRole = old('role', $user->hasRole('admin') ? 'admin' : 'receptionist'))
            <select id="role" name="role" class="border rounded ps-3 pe-10 py-2 block w-full" required>
                <option value="receptionist" @selected($currentRole === 'receptionist')>{{ __('Receptionist') }}</option>
                <option value="admin" @selected($currentRole === 'admin')>{{ __('Admin') }}</option>
            </select>
            @error('role') <p class="text-red-600 text-sm" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="text-label-sm text-on-surface-variant">{{ __('New Password') }}</label>
            <input id="password" name="password" type="password" autocomplete="new-password" class="border rounded ps-3 pe-3 py-2 block w-full">
            <p class="text-label-sm text-on-surface-variant">{{ __('Leave blank to keep the current password.') }}</p>
            @error('password') <p class="text-red-600 text-sm" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary"><x-icon name="save" /> {{ __('Save') }}</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-admin-page>
