<?php

use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('an admin in company mode sees all nav sections including departments and employees', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Home');
    $response->assertSee('Scan');
    $response->assertSee('Visitors');
    $response->assertSee('History');
    $response->assertSee('Admin');
    $response->assertSee('Departments');
    $response->assertSee('Employees');
    $response->assertDontSee('Companies');
});

test('an admin in building mode sees companies instead of departments and employees', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Companies');
    $response->assertDontSee('Departments');
    $response->assertDontSee('Employees');
});

test('a receptionist sees no admin section at all', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    Role::firstOrCreate(['name' => 'receptionist']);
    $user = User::factory()->create();
    $user->assignRole('receptionist');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertDontSee('Departments');
    $response->assertDontSee('Employees');
    $response->assertDontSee('Companies');
});

test('the header slot still renders on pages that pass one', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile');

    $response->assertOk();
    $response->assertSee(__('Profile'));
});

test('every existing page that used the old layout still renders under the new one', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/dashboard')->assertOk();
    $this->actingAs($admin)->get('/profile')->assertOk();
    $this->actingAs($admin)->get('/visits')->assertOk();
    $this->actingAs($admin)->get('/visits/create')->assertOk();
    $this->actingAs($admin)->get('/history')->assertOk();
    $this->actingAs($admin)->get('/admin/departments')->assertOk();
    $this->actingAs($admin)->get('/admin/employees')->assertOk();
    $this->actingAs($admin)->get('/admin/users')->assertOk();
    $this->actingAs($admin)->get('/admin/settings')->assertOk();
});
