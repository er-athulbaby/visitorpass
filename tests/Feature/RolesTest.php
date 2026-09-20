<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('admin and receptionist roles exist after seeding', function () {
    expect(Role::where('name', 'admin')->exists())->toBeTrue();
    expect(Role::where('name', 'receptionist')->exists())->toBeTrue();
});

test('a user can be assigned the admin role', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    expect($user->hasRole('admin'))->toBeTrue();
    expect($user->hasRole('receptionist'))->toBeFalse();
});

test('users default to english locale', function () {
    $user = User::factory()->create();

    expect($user->locale)->toBe('en');
});
