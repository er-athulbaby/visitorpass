<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['app.install_token' => 's3cret-token']);
});

test('with an install token configured, the install wizard is hidden without it', function () {
    Storage::fake('local');
    Artisan::call('migrate:rollback', ['--force' => true]);

    $this->get('/install')->assertNotFound();
    $this->get('/install?token=wrong')->assertNotFound();
    $this->post('/install', ['db_host' => 'evil.example'])->assertNotFound();
});

test('the install wizard opens with the right token and carries it in the form', function () {
    Storage::fake('local');
    Artisan::call('migrate:rollback', ['--force' => true]);

    $this->get('/install?token=s3cret-token')
        ->assertOk()
        ->assertSee('name="token" value="s3cret-token"', false);
});

test('with an install token configured, nobody can create the first admin without it', function () {
    $this->post('/setup', [
        'deployment_mode' => 'company',
        'admin_name' => 'Mallory',
        'admin_email' => 'mallory@example.com',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ])->assertNotFound();

    expect(User::count())->toBe(0);
});

test('setup works with the right token and the form carries it', function () {
    $this->get('/setup?token=s3cret-token')->assertOk()->assertSee('name="token" value="s3cret-token"', false);

    $this->post('/setup', [
        'token' => 's3cret-token',
        'deployment_mode' => 'company',
        'admin_name' => 'Jane',
        'admin_email' => 'jane@example.com',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ])->assertRedirect('/login');

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});
