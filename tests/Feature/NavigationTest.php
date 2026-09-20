<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('a receptionist sees a link to visits from the dashboard', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    $response = $this->actingAs($receptionist)->get('/dashboard');

    $response->assertSee(route('visits.index'), false);
});

test('an admin in company mode sees links to departments and employees but not companies', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertSee(route('admin.departments.index'), false);
    $response->assertSee(route('admin.employees.index'), false);
    $response->assertSee(route('admin.users.index'), false);
    $response->assertDontSee(route('admin.companies.index'), false);
});

test('an admin in building mode sees a link to companies but not departments', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertSee(route('admin.companies.index'), false);
    $response->assertDontSee(route('admin.departments.index'), false);
});

test('a receptionist does not see admin-only links', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    $response = $this->actingAs($receptionist)->get('/dashboard');

    $response->assertDontSee(route('admin.departments.index'), false);
    $response->assertDontSee(route('admin.users.index'), false);
});
