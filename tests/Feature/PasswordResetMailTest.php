<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('without SMTP settings, no reset link is generated or written to the log', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHasErrors(['email' => 'Password reset by email is not set up. Ask an administrator to reset your password.']);

    Notification::assertNothingSent();
});

test('with SMTP settings, the reset link goes out through SMTP rather than the log mailer', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company', 'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_from_address' => 'desk@example.test']);
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
    expect(config('mail.default'))->toBe('smtp');
    expect(config('mail.mailers.smtp.host'))->toBe('smtp.example.test');
});

test('an unknown email gets the same reply as a real one, so accounts cannot be discovered', function () {
    Notification::fake();
    Setting::create(['id' => 1, 'deployment_mode' => 'company', 'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_from_address' => 'desk@example.test']);
    $user = User::factory()->create();

    $known = $this->post('/forgot-password', ['email' => $user->email]);
    $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.test']);

    $unknown->assertSessionHasNoErrors();
    expect($unknown->getSession()->get('status'))->toBe($known->getSession()->get('status'));
});

test('password reset requests are rate limited per visitor', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    foreach (range(1, 6) as $i) {
        $this->post('/forgot-password', ['email' => "a{$i}@example.test"]);
    }

    $this->post('/forgot-password', ['email' => 'a7@example.test'])->assertStatus(429);
});
