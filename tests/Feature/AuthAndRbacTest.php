<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
});

test('a guest is redirected to login when visiting an admin route', function () {
    $response = $this->get('/admin/ping');

    $response->assertRedirect('/login');
});

test('a receptionist is forbidden from an admin route', function () {
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    $response = $this->actingAs($receptionist)->get('/admin/ping');

    $response->assertForbidden();
});

test('an admin can access an admin route', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/ping');

    $response->assertOk();
});
