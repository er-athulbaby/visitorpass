<?php

use App\Models\Setting;
use App\Models\User;

test('completing the wizard works on a completely fresh database with no roles seeded', function () {
    $response = $this->post('/setup', [
        'deployment_mode' => 'company',
        'admin_name' => 'Jane Admin',
        'admin_email' => 'jane@example.com',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ]);

    $response->assertRedirect('/login');

    $admin = User::where('email', 'jane@example.com')->first();
    expect($admin)->not->toBeNull();
    expect($admin->hasRole('admin'))->toBeTrue();
});

test('completing the wizard in company mode creates settings and an admin user', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $response = $this->post('/setup', [
        'deployment_mode' => 'company',
        'admin_name' => 'Jane Admin',
        'admin_email' => 'jane@example.com',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ]);

    $response->assertRedirect('/login');

    $setting = Setting::current();
    expect($setting)->not->toBeNull();
    expect($setting->deployment_mode)->toBe('company');

    $admin = User::where('email', 'jane@example.com')->first();
    expect($admin)->not->toBeNull();
    expect($admin->hasRole('admin'))->toBeTrue();
});

test('completing the wizard in building mode locks building mode', function () {
    $this->post('/setup', [
        'deployment_mode' => 'building',
        'admin_name' => 'Bob Admin',
        'admin_email' => 'bob@example.com',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ]);

    expect(Setting::current()->deployment_mode)->toBe('building');
});

test('wizard rejects an invalid deployment mode', function () {
    $response = $this->post('/setup', [
        'deployment_mode' => 'not-a-real-mode',
        'admin_name' => 'Jane Admin',
        'admin_email' => 'jane@example.com',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors('deployment_mode');
    expect(Setting::isComplete())->toBeFalse();
});

test('setup is inaccessible once already completed', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $response = $this->get('/setup');

    $response->assertRedirect('/login');
});
