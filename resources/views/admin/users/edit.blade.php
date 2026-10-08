<x-admin-page>
    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-sm -ms-3 mb-3"><x-icon name="arrow_back" class="icon-directional" /> {{ __('Back') }}</a>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="card max-w-2xl">
        @csrf
        @method('PUT')
        <div class="card-header"><h2 class="card-title">{{ __('Edit user') }}</h2></div>
        <div class="card-body space-y-5">

        <div>
            <label for="name" class="field-label">{{ __('Name') }}</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" class="field" required>
            @error('name') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="field-label">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" class="field" required>
            @error('email') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="role" class="field-label">{{ __('Role') }}</label>
            @php($currentRole = old('role', $user->hasRole('admin') ? 'admin' : 'receptionist'))
            <select id="role" name="role" class="field" required>
                <option value="receptionist" @selected($currentRole === 'receptionist')>{{ __('Receptionist') }}</option>
                <option value="admin" @selected($currentRole === 'admin')>{{ __('Admin') }}</option>
            </select>
            @error('role') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="field-label">{{ __('New Password') }}</label>
            <input id="password" name="password" type="password" autocomplete="new-password" class="field">
            <p class="field-help">{{ __('Leave blank to keep the current password.') }}</p>
            @error('password') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>

        </div>
        <div class="flex justify-end gap-2 border-t border-outline-variant px-5 py-4">
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary"><x-icon name="save" /> {{ __('Save') }}</button>
        </div>
    </form>
</x-admin-page>
