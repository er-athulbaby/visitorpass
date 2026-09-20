<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
});

test('a user with arabic locale gets rtl direction on the page', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $response = $this->actingAs($user)->get('/visits');

    $response->assertSee('dir="rtl"', false);
});

test('a user with english locale gets ltr direction on the page', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($user)->get('/visits');

    $response->assertSee('dir="ltr"', false);
});

test('switching locale updates the stored preference', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($user)->patch('/locale', ['locale' => 'ar']);

    $response->assertRedirect();
    expect($user->fresh()->locale)->toBe('ar');
});
