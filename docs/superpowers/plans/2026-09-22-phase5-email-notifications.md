# Phase 5: Email Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a visitor checks in, email the host (company mode: the employee) or company contact (building mode) using per-installation SMTP settings, sent synchronously with no queue infrastructure required.

**Architecture:** A `NotificationMailer` service turns a `Setting` row's `smtp_*` columns into a live mailer at request time (the same runtime-config pattern already used for MySQL in the Phase 4 install wizard), used both by the check-in trigger in `VisitController::store()` and by a new admin "send test email" action. Two plain Mailables (`VisitorCheckedIn`, `TestEmail`) carry the content; neither depends on `Setting::current()` for styling, matching how the install-wizard and outage views already avoid that dependency for anything rendered outside the normal authenticated app shell.

**Tech Stack:** Laravel 13, Pest, `Mail::fake()` for testing (no real SMTP in tests), Eloquent's built-in `encrypted` cast.

**Spec:** [docs/superpowers/specs/2026-09-22-phase5-email-notifications-design.md](../specs/2026-09-22-phase5-email-notifications-design.md)

## Global Constraints

- No queueing — every send happens synchronously, inline in the request that triggers it (spec Non-goals).
- No checkout notification — check-in only.
- No enable/disable toggle separate from SMTP configuration — live once `smtp_host` is non-empty, silent otherwise.
- Email views never call `Setting::current()` or otherwise depend on branding — they render outside the app shell entirely.
- SMTP password is encrypted at rest via Eloquent's `encrypted` cast on `smtp_password_encrypted`, keyed to the installation's own `APP_KEY`.
- A blank recipient email or blank `smtp_host` must never raise an error or change the check-in response — both are documented "do nothing" cases, not failures.
- An SMTP send failure (bad creds, host down, timeout) is logged via `Log::warning` and swallowed during check-in; the receptionist always sees the normal success message.

## Review Focus

- **Employee/company has no email on file** — check-in must succeed exactly as it does today, with no notification attempt and no visible change to the receptionist. Covered in Task 2.
- **`smtp_host` is blank (fresh install, email not configured yet)** — `NotificationMailer::send()` must return `false` and make no send attempt at all, not attempt to connect with empty credentials. Covered in Task 1.
- **Saving settings with the password field left blank** — must preserve the previously-saved password, not overwrite it with an empty string (the field always renders blank for security, so "blank" is the common case on every re-save that doesn't change the password). Covered in Task 3.
- **A real SMTP send throws during check-in** (bad credentials, unreachable host) — must not surface to the receptionist or break the check-in response; only a log entry. Covered in Task 2.
- **Admin clicks "send test email" before ever saving SMTP settings** — `Setting::current()->smtp_host` is null, so the test-email action must handle "nothing configured yet" as a failure case with a clear message, not a blank success or a crash. Covered in Task 4.

---

## File Structure

- `app/Services/NotificationMailer.php` — new. The single place that configures the `smtp` mailer at runtime from a `Setting` row and sends a given Mailable, returning `bool`.
- `app/Mail/VisitorCheckedIn.php` — new. Carries a `Visit` (with `visitor`/`employee`/`company` eager-loaded by the caller).
- `resources/views/emails/visitor-checked-in.blade.php` — new. Plain content: visitor name, company, arrival time.
- `app/Mail/TestEmail.php` — new. Trivial, one-line body confirming settings work.
- `resources/views/emails/test-email.blade.php` — new.
- `app/Models/Setting.php` — modify. Add the `encrypted` cast.
- `app/Http/Controllers/VisitController.php` — modify. `store()` resolves the recipient and calls `NotificationMailer::send()` after creating the visit.
- `app/Http/Controllers/Admin/SettingsController.php` — modify. `update()` persists the new SMTP fields (with the password blank-means-keep-existing rule); new `testEmail()` action.
- `app/Http/Requests/UpdateSettingsRequest.php` — modify. Add the five new nullable SMTP validation rules.
- `routes/web.php` — modify. Add `POST admin/settings/test-email`.
- `resources/views/admin/settings/edit.blade.php` — modify. New "Email Notifications" section plus the test-email button.
- `README.md` — modify. Short "Email notifications" subsection.
- Tests: `tests/Feature/NotificationMailerTest.php`, `tests/Feature/VisitCheckInNotificationTest.php`, `tests/Feature/SettingsSmtpTest.php`, `tests/Feature/TestEmailTest.php` — all new.

---

### Task 1: `NotificationMailer` service and `Setting`'s encrypted cast

**Files:**
- Create: `app/Services/NotificationMailer.php`
- Modify: `app/Models/Setting.php`
- Test: `tests/Feature/NotificationMailerTest.php`

**Interfaces:**
- Produces: `App\Services\NotificationMailer::send(Setting $setting, Mailable $mailable, string $to): bool`. Later tasks (2, 4) call this exact signature.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\Setting;
use App\Services\NotificationMailer;
use Illuminate\Mail\Mailable;
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/NotificationMailerTest.php`
Expected: FAIL with "Class App\Services\NotificationMailer not found"

- [ ] **Step 3: Add the encrypted cast to `Setting`**

Modify `app/Models/Setting.php`, adding this method alongside the existing `fillable`/methods:

```php
    protected $casts = [
        'smtp_password_encrypted' => 'encrypted',
    ];
```

- [ ] **Step 4: Write `NotificationMailer`**

```php
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

        try {
            Config::set('mail.mailers.smtp.host', $setting->smtp_host);
            Config::set('mail.mailers.smtp.port', $setting->smtp_port);
            Config::set('mail.mailers.smtp.username', $setting->smtp_username);
            // Reading this attribute decrypts it via the `encrypted` cast,
            // which can throw — must stay inside this try.
            Config::set('mail.mailers.smtp.password', $setting->smtp_password_encrypted);
            Config::set('mail.from.address', $setting->smtp_from_address);
            // Fail fast on an unreachable/firewalled host instead of hanging
            // for PHP's default socket timeout.
            Config::set('mail.mailers.smtp.timeout', 5);

            Mail::mailer('smtp')->to($to)->send($mailable);

            return true;
        } catch (Throwable $e) {
            Log::warning('Email notification failed: '.$e->getMessage());

            return false;
        }
    }
}
```

Note: `$setting->smtp_password_encrypted` reads as the **decrypted** plain
password — Eloquent's `encrypted` cast (added in Step 3) decrypts
transparently on access, so this is correct despite the attribute's name.
(Corrected post-review: the `Config::set` block and the explicit timeout
must be inside the `try` — an earlier version of this plan put them
outside it, which would let an `APP_KEY` change or an unreachable host
break check-in instead of degrading silently.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/NotificationMailerTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Services/NotificationMailer.php app/Models/Setting.php tests/Feature/NotificationMailerTest.php
git commit -m "feat: add NotificationMailer service and encrypt SMTP password at rest"
```

---

### Task 2: `VisitorCheckedIn` mailable and check-in trigger

**Files:**
- Create: `app/Mail/VisitorCheckedIn.php`
- Create: `resources/views/emails/visitor-checked-in.blade.php`
- Modify: `app/Http/Controllers/VisitController.php`
- Test: `tests/Feature/VisitCheckInNotificationTest.php`

**Interfaces:**
- Consumes: `App\Services\NotificationMailer::send(Setting $setting, Mailable $mailable, string $to): bool` (Task 1).
- Produces: nothing consumed by later tasks in this plan.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Mail\VisitorCheckedIn;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('company mode sends a check-in email to the selected employee', function () {
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
    $user = User::factory()->create();
    $department = Department::create(['name' => 'Engineering']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Host Person', 'email' => 'host@example.com']);

    $this->actingAs($user)->post('/visits', [
        'cpr_number' => '900000000',
        'name' => 'Jane Visitor',
        'company_name' => 'Acme Inc',
        'employee_id' => $employee->id,
    ]);

    Mail::assertSent(VisitorCheckedIn::class, fn ($mail) => $mail->hasTo('host@example.com'));
});

test('building mode sends a check-in email to the selected company contact', function () {
    Mail::fake();

    Setting::create([
        'id' => 1,
        'deployment_mode' => 'building',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'notify@example.com',
        'smtp_password_encrypted' => 'secret',
        'smtp_from_address' => 'notify@example.com',
    ]);
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Acme Inc', 'contact_email' => 'contact@acme.test']);

    $this->actingAs($user)->post('/visits', [
        'cpr_number' => '900000001',
        'name' => 'Jane Visitor',
        'company_id' => $company->id,
    ]);

    Mail::assertSent(VisitorCheckedIn::class, fn ($mail) => $mail->hasTo('contact@acme.test'));
});

test('check-in succeeds and sends nothing when the employee has no email on file', function () {
    Mail::fake();

    Setting::create([
        'id' => 1,
        'deployment_mode' => 'company',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_from_address' => 'notify@example.com',
    ]);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'Engineering']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Host Person', 'email' => null]);

    $response = $this->actingAs($user)->post('/visits', [
        'cpr_number' => '900000002',
        'name' => 'Jane Visitor',
        'employee_id' => $employee->id,
    ]);

    $response->assertRedirect(route('visits.index'));
    Mail::assertNothingSent();
});

test('check-in succeeds even when the SMTP send throws', function () {
    Setting::create([
        'id' => 1,
        'deployment_mode' => 'company',
        'smtp_host' => 'smtp.invalid-host-does-not-exist.test',
        'smtp_port' => 587,
        'smtp_username' => 'notify@example.com',
        'smtp_password_encrypted' => 'secret',
        'smtp_from_address' => 'notify@example.com',
    ]);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'Engineering']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Host Person', 'email' => 'host@example.com']);

    $response = $this->actingAs($user)->post('/visits', [
        'cpr_number' => '900000003',
        'name' => 'Jane Visitor',
        'employee_id' => $employee->id,
    ]);

    $response->assertRedirect(route('visits.index'))->assertSessionHas('status');
});
```

Note: the last test does **not** call `Mail::fake()` — it deliberately lets
a real (failing) SMTP connection attempt happen against a host that
cannot resolve, to prove the try/catch in `NotificationMailer::send()`
(Task 1) actually protects the check-in response. This will be slow
(DNS/connect timeout, typically a few seconds) but must still pass.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/VisitCheckInNotificationTest.php`
Expected: FAIL — `VisitorCheckedIn` class doesn't exist, and no email is ever sent since `VisitController::store()` doesn't trigger anything yet.

- [ ] **Step 3: Write the mailable**

```php
<?php

namespace App\Mail;

use App\Models\Visit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VisitorCheckedIn extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Visit $visit)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Visitor arrived: '.$this->visit->visitor->name)
            ->view('emails.visitor-checked-in');
    }
}
```

- [ ] **Step 4: Write the email view**

```blade
<p>{{ $visit->visitor->name }} has arrived{{ $visit->visitor->company_name ? ' from '.$visit->visitor->company_name : '' }}.</p>
<p>Checked in at {{ $visit->check_in_at->format('M j, Y g:i A') }}.</p>
```

- [ ] **Step 5: Wire the trigger into `VisitController::store()`**

Modify `app/Http/Controllers/VisitController.php`. Add imports:

```php
use App\Mail\VisitorCheckedIn;
use App\Services\NotificationMailer;
```

Replace the end of `store()` (from `$visitor->visits()->create([...]);` onward) with:

```php
        $visit = $visitor->visits()->create([
            'mobile_number' => $validated['mobile_number'] ?? null,
            'employee_id' => $validated['employee_id'] ?? null,
            'department_id' => $request->departmentIdForEmployee(),
            'company_id' => $validated['company_id'] ?? null,
            'check_in_at' => now(),
        ]);

        $mode = Setting::current()->deployment_mode;

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

        return redirect()->route('visits.index')
            ->with('status', __('Visitor checked in.'));
```

(`Company`, `Employee`, and `Setting` are already imported at the top of
this file.)

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/VisitCheckInNotificationTest.php`
Expected: PASS (4 tests)

- [ ] **Step 7: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Mail/VisitorCheckedIn.php resources/views/emails/visitor-checked-in.blade.php app/Http/Controllers/VisitController.php tests/Feature/VisitCheckInNotificationTest.php
git commit -m "feat: send a check-in notification email to the host or company contact"
```

---

### Task 3: Settings UI persists SMTP fields

**Files:**
- Modify: `app/Http/Requests/UpdateSettingsRequest.php`
- Modify: `app/Http/Controllers/Admin/SettingsController.php`
- Modify: `resources/views/admin/settings/edit.blade.php`
- Test: `tests/Feature/SettingsSmtpTest.php`

**Interfaces:**
- Consumes: nothing new from earlier tasks.
- Produces: nothing consumed by later tasks (Task 4's test-email button reads `Setting::current()` directly, already available).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    Role::firstOrCreate(['name' => 'admin']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admin can save smtp settings including the password', function () {
    $response = $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => '#0F172A',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'notify@example.com',
        'smtp_password' => 'first-password',
        'smtp_from_address' => 'notify@example.com',
    ]);

    $response->assertRedirect(route('admin.settings.edit'));

    $setting = Setting::current();
    expect($setting->smtp_host)->toBe('smtp.example.com');
    expect($setting->smtp_password_encrypted)->toBe('first-password');
});

test('leaving the password blank on a later save keeps the previously-saved password', function () {
    Setting::current()->update([
        'smtp_host' => 'smtp.example.com',
        'smtp_password_encrypted' => 'original-password',
    ]);

    $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => '#0F172A',
        'smtp_host' => 'smtp.example.com',
        'smtp_username' => 'notify@example.com',
        'smtp_password' => '',
        'smtp_from_address' => 'notify@example.com',
    ]);

    expect(Setting::current()->smtp_password_encrypted)->toBe('original-password');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/SettingsSmtpTest.php`
Expected: FAIL — validation rejects the unknown `smtp_*` fields is not the
issue (extra fields are simply ignored by Laravel's validator unless
listed), but `Setting::current()->smtp_host` stays null since
`SettingsController::update()` doesn't persist them yet.

- [ ] **Step 3: Add validation rules**

Modify `app/Http/Requests/UpdateSettingsRequest.php`, adding to the
`rules()` array:

```php
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'between:1,65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_from_address' => ['nullable', 'email', 'max:255'],
```

- [ ] **Step 4: Persist the fields in the controller**

Modify `app/Http/Controllers/Admin/SettingsController.php`, in `update()`,
after the existing `$data` array is built and before
`Setting::current()->update($data);`:

```php
        foreach (['smtp_host', 'smtp_port', 'smtp_username', 'smtp_from_address'] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $validated[$field];
            }
        }

        if (! empty($validated['smtp_password'])) {
            $data['smtp_password_encrypted'] = $validated['smtp_password'];
        }
```

(Corrected post-review: using `array_key_exists` instead of `?? null`
means a future request that omits these fields entirely — a different
form, an API call — doesn't silently wipe previously-saved SMTP
settings. The single form this plan builds always posts all fields, so
this was latent rather than user-visible, but it's a silent-data-loss
trap for whoever adds the next settings entry point.)

- [ ] **Step 5: Add the fields to the settings view**

Modify `resources/views/admin/settings/edit.blade.php`, inserting before
the closing `<button type="submit">` line:

```blade
            <h2 class="text-lg font-semibold mt-6 mb-2">{{ __('Email Notifications') }}</h2>

            <div class="mb-4">
                <label for="smtp_host">{{ __('SMTP Host') }}</label>
                <input id="smtp_host" name="smtp_host" type="text" value="{{ old('smtp_host', $setting->smtp_host) }}" class="border rounded ps-3 pe-3 py-2 block w-full">
                @error('smtp_host')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="smtp_port">{{ __('SMTP Port') }}</label>
                <input id="smtp_port" name="smtp_port" type="text" value="{{ old('smtp_port', $setting->smtp_port) }}" class="border rounded ps-3 pe-3 py-2 block w-full">
                @error('smtp_port')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="smtp_username">{{ __('SMTP Username') }}</label>
                <input id="smtp_username" name="smtp_username" type="text" value="{{ old('smtp_username', $setting->smtp_username) }}" class="border rounded ps-3 pe-3 py-2 block w-full">
                @error('smtp_username')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="smtp_password">{{ __('SMTP Password') }}</label>
                <input id="smtp_password" name="smtp_password" type="password" value="" class="border rounded ps-3 pe-3 py-2 block w-full">
                <p class="text-gray-500 text-sm">{{ __('Leave blank to keep the current password.') }}</p>
                @error('smtp_password')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="smtp_from_address">{{ __('From Address') }}</label>
                <input id="smtp_from_address" name="smtp_from_address" type="text" value="{{ old('smtp_from_address', $setting->smtp_from_address) }}" class="border rounded ps-3 pe-3 py-2 block w-full">
                @error('smtp_from_address')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/SettingsSmtpTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/UpdateSettingsRequest.php app/Http/Controllers/Admin/SettingsController.php resources/views/admin/settings/edit.blade.php tests/Feature/SettingsSmtpTest.php
git commit -m "feat: persist SMTP settings with password-preserving updates"
```

---

### Task 4: "Send test email" admin action

**Files:**
- Create: `app/Mail/TestEmail.php`
- Create: `resources/views/emails/test-email.blade.php`
- Modify: `app/Http/Controllers/Admin/SettingsController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/settings/edit.blade.php`
- Test: `tests/Feature/TestEmailTest.php`

**Interfaces:**
- Consumes: `App\Services\NotificationMailer::send(Setting $setting, Mailable $mailable, string $to): bool` (Task 1).
- Produces: route `admin.settings.test-email` (`POST admin/settings/test-email`).

- [ ] **Step 1: Write the failing tests**

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/TestEmailTest.php`
Expected: FAIL with a 404 (route doesn't exist yet).

- [ ] **Step 3: Write the mailable and view**

`app/Mail/TestEmail.php`:

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function build(): self
    {
        return $this
            ->subject('VisitorPass test email')
            ->view('emails.test-email');
    }
}
```

`resources/views/emails/test-email.blade.php`:

```blade
<p>This is a test email from VisitorPass. Your SMTP settings are working.</p>
```

- [ ] **Step 4: Add the controller action**

Modify `app/Http/Controllers/Admin/SettingsController.php`. Add imports:

```php
use App\Mail\TestEmail;
use App\Services\NotificationMailer;
```

Add the method:

```php
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
```

- [ ] **Step 5: Add the route**

Modify `routes/web.php`, inside the existing `auth`+`admin` group, next to
the current `settings.edit`/`settings.update` routes:

```php
    Route::post('settings/test-email', [SettingsController::class, 'testEmail'])->name('settings.test-email');
```

- [ ] **Step 6: Add the button to the view**

Modify `resources/views/admin/settings/edit.blade.php`, adding this
directly after the closing `</form>` tag:

```blade
        <form method="POST" action="{{ route('admin.settings.test-email') }}" class="mt-2">
            @csrf
            @if (session('error'))
                <p class="text-red-600 mb-2">{{ session('error') }}</p>
            @endif
            <button type="submit" class="bg-gray-200 text-gray-800 rounded px-4 py-2">{{ __('Send test email') }}</button>
        </form>
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/TestEmailTest.php`
Expected: PASS (3 tests)

- [ ] **Step 8: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add app/Mail/TestEmail.php resources/views/emails/test-email.blade.php app/Http/Controllers/Admin/SettingsController.php routes/web.php resources/views/admin/settings/edit.blade.php tests/Feature/TestEmailTest.php
git commit -m "feat: add send-test-email action to admin settings"
```

---

### Task 5: README documentation

**Files:**
- Modify: `README.md`

**Interfaces:**
- Consumes: nothing (documentation only).
- Produces: nothing (terminal task).

- [ ] **Step 1: Add the Email Notifications section**

Modify `README.md`, adding this after the existing feature list / mode
description near the top (or after the Installation section — place it
wherever the file's structure reads best, as its own `##` heading):

```markdown
## Email notifications

Configure SMTP under **Admin > Settings** — host, port, username,
password, and a from address. Once a host is set, every visitor check-in
emails the host (company mode) or the company's contact (building mode),
if that person has an email on file. Use **Send test email** on the same
screen to confirm your settings work before relying on them; if it's
wrong, check-in still succeeds silently and nothing is sent.
```

- [ ] **Step 2: Verify the file renders sensibly**

Run: `cat README.md` (or open it) and confirm no broken Markdown around
the edited section.

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: document email notification setup and the test-email button"
```

---

## Final Steps

- [ ] Run `php artisan test` once more for the whole suite.
- [ ] Run `npm run build` to confirm nothing broke (this phase touches no frontend assets, but confirm anyway).
- [ ] Manually verify locally against the local MySQL instance: save real SMTP settings (or a fake host to confirm the failure path), click "Send test email", then perform a real check-in and confirm the behavior matches what the tests assert.
- [ ] Push to `main` per this project's established workflow (direct commits, no feature branches).
