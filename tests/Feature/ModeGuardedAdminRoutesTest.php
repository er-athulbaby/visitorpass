<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('department and employee admin routes are not available in building mode', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);

    $this->actingAs($this->admin)->get('/admin/departments')->assertNotFound();
    $this->actingAs($this->admin)->get('/admin/employees')->assertNotFound();
});

test('company admin routes are not available in company mode', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $this->actingAs($this->admin)->get('/admin/companies')->assertNotFound();
});
