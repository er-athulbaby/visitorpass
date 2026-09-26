<?php

use App\Models\Setting;
use App\Models\User;

test('the app layout renders css variables from the current settings', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company', 'primary_color' => '#EF4135']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertSee('--color-primary: #EF4135', false);
    $response->assertSee('--color-on-primary: #000000', false);
});

test('the setup wizard page renders the default theme before any settings exist', function () {
    $response = $this->get('/setup');

    $response->assertOk();
    $response->assertSee('--color-primary: #0F172A', false);
    $response->assertSee('--color-on-primary: #FFFFFF', false);
});

test('the app layout falls back to the default favicon when none is set', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertSee('favicon.ico', false);
});

test('the primary button component on the login screen uses the brand color', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company', 'primary_color' => '#EF4135']);

    $response = $this->get('/login');

    $response->assertSee('bg-primary', false);
    $response->assertSee('text-on-primary', false);
    $response->assertDontSee('bg-gray-800', false);
});

test('logo and favicon URLs come from the public disk, so they keep a subfolder like /building', function () {
    config(['filesystems.disks.public.url' => 'https://example.test/building/storage']);
    \App\Models\Setting::create(['id' => 1, 'deployment_mode' => 'company', 'logo_path' => 'branding/logo.png', 'favicon_path' => 'branding/icon.png']);

    $this->get('/login')
        ->assertSee('https://example.test/building/storage/branding/logo.png', false)
        ->assertSee('https://example.test/building/storage/branding/icon.png', false);
});
