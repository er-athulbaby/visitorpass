<?php

use App\Models\Setting;
use App\Services\NotificationMailer;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DummyTestMailable extends Mailable
{
    public function build(): self
    {
        return $this->subject('Dummy')->html('<p>hello</p>');
    }
}

test('send does nothing and returns false when smtp_host is blank', function () {
    Mail::fake();

    $setting = Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $result = (new NotificationMailer)->send($setting, new DummyTestMailable, 'someone@example.com');

    expect($result)->toBeFalse();
    Mail::assertNothingSent();
});

test('send configures the mailer from the setting and sends the given mailable', function () {
    Mail::fake();

    $setting = Setting::create([
        'id' => 1,
        'deployment_mode' => 'company',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'notify@example.com',
        'smtp_password_encrypted' => 'secret-password',
        'smtp_from_address' => 'notify@example.com',
    ]);

    $result = (new NotificationMailer)->send($setting, new DummyTestMailable, 'someone@example.com');

    expect($result)->toBeTrue();
    Mail::assertSent(DummyTestMailable::class, fn ($mail) => $mail->hasTo('someone@example.com'));
    expect(config('mail.mailers.smtp.host'))->toBe('smtp.example.com');
    expect(config('mail.mailers.smtp.password'))->toBe('secret-password');
});

test('send configures a timeout so an unreachable host cannot hang the request', function () {
    Mail::fake();

    $setting = Setting::create([
        'id' => 1,
        'deployment_mode' => 'company',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'notify@example.com',
        'smtp_password_encrypted' => 'secret-password',
        'smtp_from_address' => 'notify@example.com',
    ]);

    (new NotificationMailer)->send($setting, new DummyTestMailable, 'someone@example.com');

    expect(config('mail.mailers.smtp.timeout'))->not->toBeNull();
});

test('send returns false instead of throwing when the stored password cannot be decrypted', function () {
    Setting::create([
        'id' => 1,
        'deployment_mode' => 'company',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'notify@example.com',
        'smtp_from_address' => 'notify@example.com',
    ]);

    // Write garbage directly to the column, bypassing the encrypted cast, to
    // simulate a value that was encrypted under a different APP_KEY (e.g.
    // after key:generate ran again, or a DB restored onto a different install).
    DB::table('settings')->where('id', 1)->update(['smtp_password_encrypted' => 'not-valid-ciphertext']);
    $setting = Setting::current();

    $result = (new NotificationMailer)->send($setting, new DummyTestMailable, 'someone@example.com');

    expect($result)->toBeFalse();
});
