<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company', 'primary_color' => '#0F172A']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('a receptionist cannot access the settings screen', function () {
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    $response = $this->actingAs($receptionist)->get('/admin/settings');

    $response->assertForbidden();
});

test('admin can view the settings screen', function () {
    $response = $this->actingAs($this->admin)->get('/admin/settings');

    $response->assertOk();
    $response->assertSee('name="primary_color"', false);
    $response->assertSee('value="#0F172A"', false);
});

test('admin can update the primary color', function () {
    $response = $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => '#EF4135',
        'tagline' => 'Visitor | BOC',
    ]);

    $response->assertRedirect('/admin/settings');
    expect(Setting::current()->primary_color)->toBe('#EF4135');
    expect(Setting::current()->tagline)->toBe('Visitor | BOC');
});

test('an invalid hex color is rejected and does not change the stored value', function () {
    $response = $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => 'not-a-color',
    ]);

    $response->assertSessionHasErrors('primary_color');
    expect(Setting::current()->primary_color)->toBe('#0F172A');
});

test('admin can upload a logo', function () {
    Storage::fake('public');

    $response = $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => '#0F172A',
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $response->assertRedirect('/admin/settings');
    $path = Setting::current()->logo_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('admin can upload an ico file as the favicon', function () {
    Storage::fake('public');

    $response = $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => '#0F172A',
        'favicon' => UploadedFile::fake()->create('favicon.ico', 10),
    ]);

    $response->assertRedirect('/admin/settings');
    $path = Setting::current()->favicon_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('a non-image file is rejected as a logo upload', function () {
    Storage::fake('public');

    $response = $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => '#0F172A',
        'logo' => UploadedFile::fake()->create('not-an-image.pdf', 10),
    ]);

    $response->assertSessionHasErrors('logo');
    expect(Setting::current()->logo_path)->toBeNull();
});

test('updating without a new logo file keeps the existing logo path', function () {
    Storage::fake('public');
    Setting::current()->update(['logo_path' => 'branding/existing-logo.png']);

    $response = $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => '#0F172A',
        'tagline' => 'Updated tagline only',
    ]);

    $response->assertRedirect('/admin/settings');
    expect(Setting::current()->logo_path)->toBe('branding/existing-logo.png');
});
