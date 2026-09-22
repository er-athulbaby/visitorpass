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
        if (empty($setting->smtp_host)) {
            return false;
        }

        Config::set('mail.mailers.smtp.host', $setting->smtp_host);
        Config::set('mail.mailers.smtp.port', $setting->smtp_port);
        Config::set('mail.mailers.smtp.username', $setting->smtp_username);
        Config::set('mail.mailers.smtp.password', $setting->smtp_password_encrypted);
        Config::set('mail.from.address', $setting->smtp_from_address);

        try {
            Mail::mailer('smtp')->to($to)->send($mailable);

            return true;
        } catch (Throwable $e) {
            Log::warning('Email notification failed: '.$e->getMessage());

            return false;
        }
    }
}
