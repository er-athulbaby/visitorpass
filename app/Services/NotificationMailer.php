<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationMailer
{
    public function send(Setting $setting, Mailable $mailable, string $to): bool
    {
        if (! $this->configure($setting)) {
            return false;
        }

        try {
            Mail::mailer('smtp')->to($to)->send($mailable);

            return true;
        } catch (Throwable $e) {
            Log::warning('Email notification failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Point this request's mail config at the SMTP settings saved in the admin
     * panel and make SMTP the default mailer, so framework mail (password reset)
     * uses it too instead of .env's MAIL_MAILER. Returns false when no SMTP is set.
     */
    public function configure(?Setting $setting): bool
    {
        if (empty($setting?->smtp_host)) {
            return false;
        }

        try {
            Config::set('mail.mailers.smtp.host', $setting->smtp_host);
            Config::set('mail.mailers.smtp.port', $setting->smtp_port);
            Config::set('mail.mailers.smtp.username', $setting->smtp_username);
            // Reading this attribute decrypts it via Setting's `encrypted` cast,
            // which can throw (e.g. after an APP_KEY change) — must stay inside
            // this try so a broken value degrades to "email not sent" rather
            // than a 500 that takes down the check-in it's attached to.
            Config::set('mail.mailers.smtp.password', $setting->smtp_password_encrypted);
            Config::set('mail.from.address', $setting->smtp_from_address);
            // A firewalled or unresponsive host must fail fast, not hang for
            // PHP's ~60s default socket timeout and risk hitting the server's
            // max_execution_time with an uncatchable fatal error.
            Config::set('mail.mailers.smtp.timeout', 5);
            Config::set('mail.default', 'smtp');

            return true;
        } catch (Throwable $e) {
            Log::warning('Email notification failed: '.$e->getMessage());

            return false;
        }
    }
}
