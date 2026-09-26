<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::with('roles')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'receptionist'])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('status', __('User created.'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'receptionist'])],
        ]);

        // Only admins can reach this form, so blocking your own role change is
        // what guarantees there is always at least one admin left.
        if ($user->is($request->user()) && ! $user->hasRole($validated['role'])) {
            return redirect()->route('admin.users.edit', $user)
                ->with('error', __('You cannot change your own role.'));
        }

        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        return redirect()->route('admin.users.index')
            ->with('status', __('User updated.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return redirect()->route('admin.users.index')
                ->with('error', __('You cannot delete your own account.'));
        }

        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            return redirect()->route('admin.users.index')
                ->with('error', __('Cannot delete the last remaining admin.'));
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('status', __('User removed.'));
    }
}
