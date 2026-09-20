<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admin can create a receptionist user', function () {
    $response = $this->actingAs($this->admin)->post('/admin/users', [
        'name' => 'New Receptionist',
        'email' => 'reception@example.com',
        'password' => 'password123',
        'role' => 'receptionist',
    ]);

    $response->assertRedirect('/admin/users');
    $user = User::where('email', 'reception@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('receptionist'))->toBeTrue();
});

test('admin can deactivate a user by deleting them', function () {
    $user = User::factory()->create();
    $user->assignRole('receptionist');

    $response = $this->actingAs($this->admin)->delete("/admin/users/{$user->id}");

    $response->assertRedirect('/admin/users');
    expect(User::find($user->id))->toBeNull();
});

test('admin cannot delete their own account', function () {
    $response = $this->actingAs($this->admin)->delete("/admin/users/{$this->admin->id}");

    $response->assertSessionHas('error');
    expect(User::find($this->admin->id))->not->toBeNull();
});

test('admin cannot delete the last remaining admin', function () {
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('admin');

    $response = $this->actingAs($this->admin)->delete("/admin/users/{$otherAdmin->id}");

    $response->assertRedirect('/admin/users');
    expect(User::find($otherAdmin->id))->toBeNull();

    $response = $this->actingAs($this->admin)->delete("/admin/users/{$this->admin->id}");

    $response->assertSessionHas('error');
    expect(User::find($this->admin->id))->not->toBeNull();
});

test('a receptionist cannot access the users admin screen', function () {
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    $response = $this->actingAs($receptionist)->get('/admin/users');

    $response->assertForbidden();
});
