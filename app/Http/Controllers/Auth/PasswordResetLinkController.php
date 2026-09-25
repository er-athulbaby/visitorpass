<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Setting;
use App\Services\NotificationMailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Reset links must go out through the SMTP saved in Settings; without
        // it Laravel falls back to MAIL_MAILER=log and writes the link to disk.
        if (! app(NotificationMailer::class)->configure(Setting::current())) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('Password reset by email is not set up. Ask an administrator to reset your password.')]);
        }

        try {
            Password::sendResetLink($request->only('email'));
        } catch (Throwable $e) {
            Log::warning('Password reset email failed: '.$e->getMessage());
        }

        // Same reply whether or not the account exists (or the send failed), so
        // this form cannot be used to find out which emails have accounts.
        return back()->with('status', __('passwords.sent'));
    }
}
