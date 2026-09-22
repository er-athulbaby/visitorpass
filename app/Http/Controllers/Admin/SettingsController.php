<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Mail\TestEmail;
use App\Models\Setting;
use App\Services\NotificationMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'setting' => Setting::current(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $data = [
            'primary_color' => $validated['primary_color'],
            'tagline' => $validated['tagline'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('branding', 'public');
        }

        if ($request->hasFile('favicon')) {
            $data['favicon_path'] = $request->file('favicon')->store('branding', 'public');
        }

        $data['smtp_host'] = $validated['smtp_host'] ?? null;
        $data['smtp_port'] = $validated['smtp_port'] ?? null;
        $data['smtp_username'] = $validated['smtp_username'] ?? null;
        $data['smtp_from_address'] = $validated['smtp_from_address'] ?? null;

        if (! empty($validated['smtp_password'])) {
            $data['smtp_password_encrypted'] = $validated['smtp_password'];
        }

        Setting::current()->update($data);

        return redirect()->route('admin.settings.edit')
            ->with('status', __('Settings updated.'));
    }

    public function testEmail(NotificationMailer $mailer): RedirectResponse
    {
        $setting = Setting::current();

        if (empty($setting->smtp_from_address)) {
            return redirect()->route('admin.settings.edit')
                ->with('error', __('Set a From Address before sending a test email.'));
        }

        $sent = $mailer->send($setting, new TestEmail, $setting->smtp_from_address);

        return $sent
            ? redirect()->route('admin.settings.edit')->with('status', __('Test email sent.'))
            : redirect()->route('admin.settings.edit')->with('error', __('Could not send the test email. Check your SMTP settings.'));
    }
}
