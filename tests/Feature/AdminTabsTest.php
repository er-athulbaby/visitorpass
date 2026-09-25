<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('every company-mode admin page shows the same tab bar with the current tab marked', function (string $url) {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $response = $this->actingAs($this->admin)->get($url);

    $response->assertOk();
    $response->assertSee('Administration');
    $response->assertSee('href="'.route('admin.departments.index').'"', false);
    $response->assertSee('href="'.route('admin.employees.index').'"', false);
    $response->assertSee('href="'.route('admin.users.index').'"', false);
    $response->assertSee('href="'.route('admin.settings.edit').'"', false);
    $response->assertDontSee(route('admin.companies.index'), false);
    $response->assertSee('href="'.url($url).'" aria-current="page"', false);
})->with(['/admin/departments', '/admin/employees', '/admin/users', '/admin/settings']);

test('building-mode admin pages show companies, users, and settings tabs only', function (string $url) {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);

    $response = $this->actingAs($this->admin)->get($url);

    $response->assertOk();
    $response->assertSee('href="'.route('admin.companies.index').'"', false);
    $response->assertSee('href="'.route('admin.users.index').'"', false);
    $response->assertSee('href="'.route('admin.settings.edit').'"', false);
    $response->assertDontSee('/admin/departments', false);
    $response->assertDontSee('/admin/employees', false);
    $response->assertSee('href="'.url($url).'" aria-current="page"', false);
})->with(['/admin/companies', '/admin/users', '/admin/settings']);

test('flash messages render once through the admin page', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $response = $this->actingAs($this->admin)->withSession(['error' => 'Something failed'])->get('/admin/settings');

    expect(substr_count($response->getContent(), 'Something failed'))->toBe(1);
});

test('the browser title names the current admin section', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $this->actingAs($this->admin)->get('/admin/employees')
        ->assertSee('<title>Employees · Administration · ', false);
});
