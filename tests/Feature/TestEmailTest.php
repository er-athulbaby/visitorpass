<?php

use App\Mail\TestEmail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('sending a test email with configured settings reports success', function () {
    Mail::fake();
    Setting::create([
        'id' => 1,
        'deployment_mode' => 'company',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'notify@example.com',
        'smtp_password_encrypted' => 'secret',
        'smtp_from_address' => 'notify@example.com',
    ]);

    $response = $this->actingAs($this->admin)->post('/admin/settings/test-email');

    $response->assertRedirect(route('admin.settings.edit'));
    $response->assertSessionHas('status');
    Mail::assertSent(TestEmail::class, fn ($mail) => $mail->hasTo('notify@example.com'));
});

test('sending a test email with no smtp configured reports failure', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $response = $this->actingAs($this->admin)->post('/admin/settings/test-email');

    $response->assertRedirect(route('admin.settings.edit'));
    $response->assertSessionHas('error');
});

test('sending a test email with a host that cannot be reached reports failure, not a crash', function () {
    // Deliberately no Mail::fake() here: this proves NotificationMailer's
    // real try/catch (Task 1) turns a genuine connection failure into a
    // clean failure flash instead of a 500.
    Setting::create([
        'id' => 1,
        'deployment_mode' => 'company',
        'smtp_host' => 'smtp.invalid-host-does-not-exist.test',
        'smtp_port' => 587,
        'smtp_username' => 'notify@example.com',
        'smtp_password_encrypted' => 'secret',
        'smtp_from_address' => 'notify@example.com',
    ]);

    $response = $this->actingAs($this->admin)->post('/admin/settings/test-email');

    $response->assertRedirect(route('admin.settings.edit'));
    $response->assertSessionHas('error');
});
