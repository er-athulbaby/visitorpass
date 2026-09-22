# Phase 5: Email Notifications — Design Spec

Date: 2026-09-22

## Purpose

The original React app sends an email to notify someone when a visitor
arrives. This phase adds the same behavior to the Laravel port: when a
visitor checks in, an email goes to whoever they're there to see — the
employee in company mode, or the company's contact in building mode —
using SMTP settings the admin configures per-installation (the `settings`
table already has unused `smtp_*` columns from Phase 1).

## Non-goals

- No queueing infrastructure. This targets non-technical buyers on cheap
  shared/cPanel hosting, where a persistent queue worker or reliable
  per-minute cron often isn't available. Email is sent synchronously,
  inline in the check-in request.
- No checkout notification — only check-in.
- No rich HTML template system, attachments, or branding (logo/color) in
  the email — plain content: visitor name, company, arrival time.
- No enable/disable toggle separate from SMTP configuration — email is
  live the moment `smtp_host` is non-empty, and silently inactive
  otherwise.
- No UI change to the visits list or check-in form beyond what's needed
  to trigger the send — this is entirely a background side effect of an
  existing action.

## Flow

1. Admin opens **Admin > Settings** (existing branding screen), fills in
   SMTP host/port/username/password/from-address in a new section, saves.
2. Admin clicks **Send test email** to confirm the settings work before
   relying on them, using the just-saved values.
3. A receptionist checks a visitor in via the existing `/visits` form.
4. After the `Visit` row is created, the app resolves who to notify:
   - Company mode: the selected `Employee`'s `email`.
   - Building mode: the selected `Company`'s `contact_email`.
5. If that email is blank, nothing happens — check-in completes exactly
   as it does today.
6. If it's set and `smtp_host` is configured, an email is sent
   synchronously through settings loaded from the `Setting` row. If the
   send throws for any reason (bad credentials, host unreachable, timeout),
   the error is logged and swallowed — the receptionist always sees the
   normal "Visitor checked in" success message.

## Components

### `App\Models\Setting` (modify)

Add an `encrypted` cast for the password column:

```php
protected $casts = [
    'smtp_password_encrypted' => 'encrypted',
];
```

Eloquent then transparently encrypts on write and decrypts on read using
the app's `APP_KEY` — no other code needs to know the value is encrypted
at rest.

### `App\Services\NotificationMailer` (new)

The single place that knows how to turn a `Setting` row into a working
mailer at runtime, since `config/mail.php` is normally static and
env-driven, but these credentials live in the database per-installation.

```php
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
        } catch (\Throwable $e) {
            Log::warning('Email notification failed: '.$e->getMessage());

            return false;
        }
    }
}
```

`send()` returns a bool so the test-email endpoint can report
success/failure without needing its own duplicate try/catch, and the
check-in flow can ignore the return value entirely.

Two implementation notes:
- `$setting->smtp_password_encrypted` reads as the **decrypted** plain
  password — Eloquent's `encrypted` cast decrypts transparently on
  access, so the attribute name is misleading but the value is correct
  to hand straight to the mailer config.
- No `encryption` (TLS/SSL) field is added to `Setting`. Laravel's SMTP
  transport (Symfony Mailer) auto-negotiates STARTTLS when the server
  advertises it, which covers standard providers like Office 365 and
  Gmail on port 587 without an explicit setting. An explicit encryption
  field is deferred until a real provider is found that needs one.

### `App\Mail\VisitorCheckedIn` (new)

A plain Mailable carrying the `Visit` (with `visitor`, `employee`,
`company` already eager-loaded by the caller). Subject: "Visitor arrived:
{visitor name}". Body view (`resources/views/emails/visitor-checked-in.blade.php`)
renders visitor name, `company_name` (if given), and `check_in_at`
formatted for readability. No branding, no layout inheritance — a
self-contained plain view, same reasoning as the install-wizard and
outage views: this must never depend on `Setting::current()` for styling,
since it's rendered as an outbound email, not a page in this app's UI.

### `App\Mail\TestEmail` (new)

A trivial Mailable: subject "VisitorPass test email", one line of body
text confirming the settings work. Separate class from
`VisitorCheckedIn` rather than a flag/parameter, since the two have
nothing in common beyond both being emails.

### `VisitController::store()` (modify)

After the existing `$visitor->visits()->create([...])` call, resolve the
recipient and attempt the send:

```php
$visit = $visitor->visits()->create([...]); // existing code, unchanged

$recipient = match ($mode) {
    'company' => Employee::find($validated['employee_id'] ?? null)?->email,
    'building' => Company::find($validated['company_id'] ?? null)?->contact_email,
    default => null,
};

if (! empty($recipient)) {
    $visit->load('visitor', 'employee', 'company');
    app(NotificationMailer::class)->send(
        Setting::current(),
        new VisitorCheckedIn($visit),
        $recipient
    );
}
```

`$mode` is already resolved once via `Setting::current()->deployment_mode`
elsewhere in this controller (see `create()`); `store()` will resolve it
the same way.

### `Admin\SettingsController` (modify)

- `edit()`: unchanged return shape (`setting` already passed to the view).
- `update()`: add the SMTP fields to `$data`, following the existing
  logo/favicon pattern for the password — only overwrite
  `smtp_password_encrypted` if the submitted `smtp_password` field is
  non-empty:

```php
$data['smtp_host'] = $validated['smtp_host'] ?? null;
$data['smtp_port'] = $validated['smtp_port'] ?? null;
$data['smtp_username'] = $validated['smtp_username'] ?? null;
$data['smtp_from_address'] = $validated['smtp_from_address'] ?? null;

if (! empty($validated['smtp_password'])) {
    $data['smtp_password_encrypted'] = $validated['smtp_password'];
}
```

- New `testEmail()` action: loads `Setting::current()`, sends a
  `TestEmail` to its own `smtp_from_address` via `NotificationMailer`,
  redirects back with a flashed success or failure message based on the
  bool `send()` returns.

### `UpdateSettingsRequest` (modify)

Add, all `nullable` (no cross-field requirements — see Non-goals):

```php
'smtp_host' => ['nullable', 'string', 'max:255'],
'smtp_port' => ['nullable', 'integer', 'between:1,65535'],
'smtp_username' => ['nullable', 'string', 'max:255'],
'smtp_password' => ['nullable', 'string', 'max:255'],
'smtp_from_address' => ['nullable', 'email', 'max:255'],
```

### Routes (modify `routes/web.php`)

```php
Route::post('settings/test-email', [SettingsController::class, 'testEmail'])
    ->name('settings.test-email');
```

Inside the existing `auth`+`admin` group, alongside the current
`settings.edit`/`settings.update` routes.

### View (`resources/views/admin/settings/edit.blade.php`, modify)

New section below the existing branding fields: the five SMTP inputs
(password field always renders empty, with helper text "leave blank to
keep the current password"), plus a "Send test email" button that posts
to `settings.test-email`.

## Security

- The SMTP password is encrypted at rest via Eloquent's `encrypted` cast,
  keyed to the installation's own `APP_KEY` — consistent with how this
  app already treats other secrets.
- The password field never round-trips the actual stored value back into
  the rendered form (always blank), so it can't leak into page source or
  browser autofill history pointing at the wrong value.
- Test-email sending uses the already-saved `Setting` row, never
  unvalidated/unsaved request input — an admin can't use the test-email
  endpoint to make the server connect to an arbitrary SMTP host without
  first passing the same validation and persistence path as a real save.

## Error handling

| Failure | Behavior |
|---|---|
| Recipient email blank | No send attempted; check-in unaffected |
| `smtp_host` blank | No send attempted; check-in unaffected |
| SMTP send throws (bad creds, host down, timeout) during check-in | Logged via `Log::warning`; check-in still succeeds normally |
| SMTP send throws during test-email | Caught the same way; test-email endpoint flashes a failure message instead of swallowing it |

## Testing plan

- `NotificationMailerTest` (unit-ish feature test, since `Mail::fake()`
  needs the app booted): `Mail::fake()`, call `send()` with a `Setting`
  that has `smtp_host` set, assert the given Mailable was sent to the
  given address. A second test with `smtp_host` blank asserts nothing
  was sent and `send()` returns `false`.
- `VisitCheckInNotificationTest` (feature): company mode with an
  employee that has an email — `Mail::fake()`, check in a visitor, assert
  `VisitorCheckedIn` was sent to that employee's address. Same for
  building mode with a company's `contact_email`. A third test: employee
  with a blank email — assert nothing was sent and the check-in still
  returns its normal success response.
- `SettingsSmtpTest` (feature): saving SMTP fields persists them (and the
  password round-trips through the `encrypted` cast correctly); leaving
  `smtp_password` blank on a subsequent save doesn't clear the
  previously-saved password.
- `TestEmailTest` (feature): `Mail::fake()` — posting to
  `settings.test-email` with a configured `Setting` asserts a `TestEmail`
  was sent and the response flashes success. A failure path: force
  `NotificationMailer::send()` to return `false` (e.g. via a bound
  fake/mock, since forcing a real SMTP failure isn't practical) and
  assert the failure flash instead.

## Documentation

- README gets a short "Email notifications" subsection: where to
  configure SMTP (Admin > Settings), that it's per-installation, and that
  the "Send test email" button is the recommended way to verify
  credentials before relying on them.
