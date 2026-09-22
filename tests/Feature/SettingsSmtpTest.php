<?php

use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    Role::firstOrCreate(['name' => 'admin']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admin can save smtp settings including the password', function () {
    $response = $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => '#0F172A',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'notify@example.com',
        'smtp_password' => 'first-password',
        'smtp_from_address' => 'notify@example.com',
    ]);

    $response->assertRedirect(route('admin.settings.edit'));

    $setting = Setting::current();
    expect($setting->smtp_host)->toBe('smtp.example.com');
    expect($setting->smtp_password_encrypted)->toBe('first-password');
});

test('leaving the password blank on a later save keeps the previously-saved password', function () {
    Setting::current()->update([
        'smtp_host' => 'smtp.example.com',
        'smtp_password_encrypted' => 'original-password',
    ]);

    $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => '#0F172A',
        'smtp_host' => 'smtp.example.com',
        'smtp_username' => 'notify@example.com',
        'smtp_password' => '',
        'smtp_from_address' => 'notify@example.com',
    ]);

    expect(Setting::current()->smtp_password_encrypted)->toBe('original-password');
});
