<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class SetupController extends Controller
{
    public function index()
    {
        return view('setup.mode');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'deployment_mode' => ['required', Rule::in(['company', 'building'])],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($validated) {
            Role::firstOrCreate(['name' => 'admin']);
            Role::firstOrCreate(['name' => 'receptionist']);

            Setting::create([
                'id' => 1,
                'deployment_mode' => $validated['deployment_mode'],
            ]);

            $admin = User::create([
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
            ]);

            $admin->assignRole('admin');
        });

        return redirect('/login')->with('status', __('Setup complete. Please log in.'));
    }
}
