# VisitorPass Laravel — Phase 3 (Branding Settings) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an admin-only Branding Settings screen (primary color, logo, favicon, tagline) that applies at runtime — no rebuild — replacing the compiled Tailwind colors with CSS custom properties read from the database on every request.

**Architecture:** `tailwind.config.js`'s `primary`/`on-primary` colors become `var(--color-primary)`/`var(--color-on-primary)`; both layouts emit those variables from `Setting::current()` in an inline `<style>` block. A new `Admin\SettingsController` handles the read/write side. No new migration — Phase 1's `settings` table already has every column.

**Tech Stack:** Laravel's public disk (`Storage::disk('public')`), a native `<input type="color">` (no JS library), WCAG relative luminance for auto text-contrast.

**Spec:** `docs/superpowers/specs/2026-09-21-visitorpass-laravel-phase3-branding-design.md`

## Global Constraints

- No auto-extraction of color from the uploaded logo — manual hex picker only.
- Old logo/favicon files are left orphaned on disk when replaced — no active cleanup.
- No public branding API endpoint — every page reads `Setting::current()` server-side directly.
- The favicon/logo/color must render correctly even before any `Setting` row exists (the setup wizard's own page, pre-auth) — fall back to the Phase 1 default theme (`#0F172A` / `#FFFFFF`).
- Submitting the settings form without a new logo/favicon file must not null out the existing `logo_path`/`favicon_path`.

## Review Focus

- Submitting an invalid hex value (e.g. `not-a-color`) — must be rejected by validation, not stored.
- Uploading a non-image file as the logo or favicon — must be rejected, not stored as a fake "image".
- A non-admin (receptionist) hitting the settings routes directly — must be blocked (403), not just hidden from nav.
- Rendering the setup wizard page itself, before any `Setting` row exists — must render the default theme, not error (`Setting::current()` returns `null` at that point).
- Updating only the tagline (no new logo/favicon files in the request) — the existing `logo_path`/`favicon_path` must survive unchanged, not be wiped to `null`.

---

## File Structure

```
app/
  Models/
    Setting.php (modified — add onPrimaryColor())
  Http/
    Controllers/
      Admin/
        SettingsController.php
      Requests/
        UpdateSettingsRequest.php
resources/
  views/
    layouts/
      app.blade.php (modified — CSS vars, favicon, logo)
      guest.blade.php (modified — CSS vars, favicon, logo, tagline)
      navigation.blade.php (modified — Settings nav link)
    admin/
      settings/
        edit.blade.php
routes/
  web.php (modified)
tailwind.config.js (modified)
tests/Feature/
  SettingContrastTest.php
  BrandingSettingsTest.php
  BrandingRenderTest.php
```

---

## Task 1: Auto-contrast helper on the Setting model

**Files:**
- Modify: `app/Models/Setting.php`
- Test: `tests/Feature/SettingContrastTest.php`

**Interfaces:**
- Consumes: `Setting::$primary_color` (Phase 1).
- Produces: `Setting::onPrimaryColor(): string` — returns `'#FFFFFF'` or `'#000000'` — consumed by Task 2's layout rendering and Task 3's admin form preview.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SettingContrastTest.php`:

```php
<?php

use App\Models\Setting;

test('a dark primary color gets white on-primary text', function () {
    $setting = new Setting(['primary_color' => '#0F172A']);

    expect($setting->onPrimaryColor())->toBe('#FFFFFF');
});

test('a light primary color gets black on-primary text', function () {
    $setting = new Setting(['primary_color' => '#FFFFFF']);

    expect($setting->onPrimaryColor())->toBe('#000000');
});

test('a mid-tone red gets a computed contrast color', function () {
    $setting = new Setting(['primary_color' => '#EF4135']);

    expect($setting->onPrimaryColor())->toBe('#FFFFFF');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/SettingContrastTest.php`
Expected: FAIL with "Call to undefined method App\Models\Setting::onPrimaryColor()"

- [ ] **Step 3: Add the method**

Modify `app/Models/Setting.php`, adding this method to the class:

```php
public function onPrimaryColor(): string
{
    $hex = ltrim($this->primary_color ?? '#0F172A', '#');

    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }

    $toLinear = fn (float $channel) => $channel <= 0.03928
        ? $channel / 12.92
        : (($channel + 0.055) / 1.055) ** 2.4;

    $r = $toLinear(hexdec(substr($hex, 0, 2)) / 255);
    $g = $toLinear(hexdec(substr($hex, 2, 2)) / 255);
    $b = $toLinear(hexdec(substr($hex, 4, 2)) / 255);

    $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

    return $luminance > 0.179 ? '#000000' : '#FFFFFF';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/SettingContrastTest.php`
Expected: PASS (3/3)

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add WCAG-based auto-contrast helper to Setting model"
```

---

## Task 2: Runtime CSS variables, logo, and favicon in both layouts

**Files:**
- Modify: `tailwind.config.js`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/layouts/guest.blade.php`
- Test: `tests/Feature/BrandingRenderTest.php`

**Interfaces:**
- Consumes: `Setting::current()` (Phase 1), `Setting::onPrimaryColor()` (Task 1).
- Produces: nothing new consumed by later tasks — this is the read side; Task 3 is the write side that changes what these layouts read.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BrandingRenderTest.php`:

```php
<?php

use App\Models\Setting;
use App\Models\User;

test('the app layout renders css variables from the current settings', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company', 'primary_color' => '#EF4135']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertSee('--color-primary: #EF4135', false);
    $response->assertSee('--color-on-primary: #FFFFFF', false);
});

test('the setup wizard page renders the default theme before any settings exist', function () {
    $response = $this->get('/setup');

    $response->assertOk();
    $response->assertSee('--color-primary: #0F172A', false);
    $response->assertSee('--color-on-primary: #FFFFFF', false);
});

test('the app layout falls back to the default favicon when none is set', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertSee('favicon.ico', false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/BrandingRenderTest.php`
Expected: FAIL (no `--color-primary` style block rendered yet)

- [ ] **Step 3: Update Tailwind config**

Modify `tailwind.config.js`:

```js
colors: {
    primary: 'var(--color-primary)',
    'on-primary': 'var(--color-on-primary)',
},
```

- [ ] **Step 4: Update the authenticated app layout**

Modify `resources/views/layouts/app.blade.php`. At the very top of the file, before `<!DOCTYPE html>`, add:

```blade
@php
    $brandSettings = \App\Models\Setting::current();
    $brandPrimary = $brandSettings->primary_color ?? '#0F172A';
    $brandOnPrimary = $brandSettings?->onPrimaryColor() ?? '#FFFFFF';
    $brandFaviconUrl = $brandSettings?->favicon_path ? \Illuminate\Support\Facades\Storage::url($brandSettings->favicon_path) : asset('favicon.ico');
    $brandLogoUrl = $brandSettings?->logo_path ? \Illuminate\Support\Facades\Storage::url($brandSettings->logo_path) : null;
@endphp
```

In the `<head>`, after the `<title>` tag, add:

```blade
<link rel="icon" href="{{ $brandFaviconUrl }}">
<style>
    :root {
        --color-primary: {{ $brandPrimary }};
        --color-on-primary: {{ $brandOnPrimary }};
    }
</style>
```

- [ ] **Step 5: Update the guest layout (login, setup wizard, password screens)**

Modify `resources/views/layouts/guest.blade.php`. At the top, before `<!DOCTYPE html>`, add the same `@php` block as Step 4. In the `<head>`, after `<title>`, add the same `<link rel="icon">` and `<style>` block as Step 4.

Then find the existing logo rendering (the `<x-application-logo>` inside an `<a href="/">`), and replace it with a conditional that uses the uploaded logo when set, plus the tagline underneath when set:

```blade
<div>
    <a href="/">
        @if ($brandLogoUrl)
            <img src="{{ $brandLogoUrl }}" alt="{{ config('app.name') }}" class="w-20 h-20 object-contain">
        @else
            <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
        @endif
    </a>
    @if ($brandSettings?->tagline)
        <p class="text-center text-sm text-gray-600 mt-2">{{ $brandSettings->tagline }}</p>
    @endif
</div>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/BrandingRenderTest.php`
Expected: PASS (3/3)

- [ ] **Step 7: Run the full suite to confirm nothing else broke**

Run: `php artisan test`
Expected: all tests pass (76 from Phases 1–2, plus 3 new from Task 1, plus 3 new from this task = 82)

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: apply branding settings via CSS variables at runtime"
```

---

## Task 3: Admin Settings screen

**Files:**
- Create: `app/Http/Requests/UpdateSettingsRequest.php`
- Create: `app/Http/Controllers/Admin/SettingsController.php`
- Create: `resources/views/admin/settings/edit.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/navigation.blade.php`
- Test: `tests/Feature/BrandingSettingsTest.php`

**Interfaces:**
- Consumes: `Setting::current()` (Phase 1), `Setting::onPrimaryColor()` (Task 1), `admin` middleware (Phase 1, Task 5).
- Produces: `PATCH /admin/settings` — updates the singleton `Setting` row — consumed by Task 2's layouts on the next request (no code dependency, just data).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BrandingSettingsTest.php`:

```php
<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company', 'primary_color' => '#0F172A']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('a receptionist cannot access the settings screen', function () {
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    $response = $this->actingAs($receptionist)->get('/admin/settings');

    $response->assertForbidden();
});

test('admin can view the settings screen', function () {
    $response = $this->actingAs($this->admin)->get('/admin/settings');

    $response->assertOk();
    $response->assertSee('#0F172A', false);
});

test('admin can update the primary color', function () {
    $response = $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => '#EF4135',
        'tagline' => 'Visitor | BOC',
    ]);

    $response->assertRedirect('/admin/settings');
    expect(Setting::current()->primary_color)->toBe('#EF4135');
    expect(Setting::current()->tagline)->toBe('Visitor | BOC');
});

test('an invalid hex color is rejected and does not change the stored value', function () {
    $response = $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => 'not-a-color',
    ]);

    $response->assertSessionHasErrors('primary_color');
    expect(Setting::current()->primary_color)->toBe('#0F172A');
});

test('admin can upload a logo', function () {
    Storage::fake('public');

    $response = $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => '#0F172A',
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $response->assertRedirect('/admin/settings');
    $path = Setting::current()->logo_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('a non-image file is rejected as a logo upload', function () {
    Storage::fake('public');

    $response = $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => '#0F172A',
        'logo' => UploadedFile::fake()->create('not-an-image.pdf', 10),
    ]);

    $response->assertSessionHasErrors('logo');
    expect(Setting::current()->logo_path)->toBeNull();
});

test('updating without a new logo file keeps the existing logo path', function () {
    Storage::fake('public');
    Setting::current()->update(['logo_path' => 'branding/existing-logo.png']);

    $response = $this->actingAs($this->admin)->patch('/admin/settings', [
        'primary_color' => '#0F172A',
        'tagline' => 'Updated tagline only',
    ]);

    $response->assertRedirect('/admin/settings');
    expect(Setting::current()->logo_path)->toBe('branding/existing-logo.png');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/BrandingSettingsTest.php`
Expected: FAIL (no route, no controller)

- [ ] **Step 3: Create the FormRequest**

```bash
php artisan make:request UpdateSettingsRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:1024'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

```bash
php artisan make:controller Admin/SettingsController
```

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
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

        Setting::current()->update($data);

        return redirect()->route('admin.settings.edit')
            ->with('status', __('Settings updated.'));
    }
}
```

- [ ] **Step 5: Create the view**

```bash
mkdir -p resources/views/admin/settings
```

Create `resources/views/admin/settings/edit.blade.php`:

```blade
<x-app-layout>
    <div class="p-6 max-w-xl">
        <h1 class="text-xl font-semibold mb-4">{{ __('Settings') }}</h1>

        @if (session('status'))
            <p class="text-green-700 mb-3">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PATCH')

            <div class="mb-4">
                <label for="primary_color">{{ __('Primary Color') }}</label>
                <input id="primary_color" name="primary_color" type="color" value="{{ old('primary_color', $setting->primary_color) }}" class="block h-10 w-20 border rounded">
                @error('primary_color')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="tagline">{{ __('Tagline') }}</label>
                <input id="tagline" name="tagline" type="text" value="{{ old('tagline', $setting->tagline) }}" class="border rounded ps-3 pe-3 py-2 block w-full">
                @error('tagline')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="logo">{{ __('Logo') }}</label>
                <input id="logo" name="logo" type="file" accept="image/*" class="block">
                @error('logo')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="favicon">{{ __('Favicon') }}</label>
                <input id="favicon" name="favicon" type="file" accept="image/*" class="block">
                @error('favicon')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Save') }}</button>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Wire the route**

In `routes/web.php`, inside the existing `admin.` route group (alongside departments/employees/companies/users):

```php
use App\Http\Controllers\Admin\SettingsController;

Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
```

- [ ] **Step 7: Add the nav link**

In `resources/views/layouts/navigation.blade.php`, inside the `@role('admin')` ... `@endrole` block (added in Phase 1, Task 12's fix pass), after the Users link:

```blade
<x-nav-link :href="route('admin.settings.edit')" :active="request()->routeIs('admin.settings.*')">
    {{ __('Settings') }}
</x-nav-link>
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/BrandingSettingsTest.php`
Expected: PASS (7/7)

- [ ] **Step 9: Run the full suite**

Run: `php artisan test`
Expected: all tests pass (82 from Tasks 1–2, plus 7 new from this task = 89)

- [ ] **Step 10: Set up the public storage symlink and rebuild assets**

```bash
php artisan storage:link
npm run build
```

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: add admin branding settings screen (color, logo, favicon, tagline)"
```

---

## Self-Review Notes

**Spec coverage:** CSS-variable architecture replacing compiled Tailwind colors → Task 2. Auto-contrast via WCAG luminance → Task 1. No public branding API (server-rendered `Setting::current()` calls) → Task 2. No favicon-repaint workaround needed (fresh server-render per page) → implicit in Task 2's approach, no special handling required. Public-disk uploads → Task 3. Admin screen (color, logo, favicon, tagline) → Task 3. Fallback to default theme before any `Setting` row exists → tested in Task 2. All "Out of Scope" items (auto-color-extraction, active file cleanup, public API) are correctly absent from every task.

**Placeholder scan:** no TBD/TODO; every step has runnable code or an exact command.

**Type consistency:** `Setting::onPrimaryColor()` (Task 1) is called identically in Task 2's layouts and exercised the same way in Task 3's tests. `$brandSettings`/`$brandPrimary`/`$brandOnPrimary`/`$brandFaviconUrl`/`$brandLogoUrl` are defined once in Task 2 and used consistently across both layout files with the same names.

**Review Focus coverage confirmed:**
- Invalid hex value → tested in Task 3 ("an invalid hex color is rejected...").
- Non-image upload → tested in Task 3 ("a non-image file is rejected as a logo upload").
- Non-admin blocked → tested in Task 3 ("a receptionist cannot access the settings screen").
- Setup wizard rendering before any `Setting` row exists → tested in Task 2 ("the setup wizard page renders the default theme...").
- Partial update not wiping existing logo/favicon → tested in Task 3 ("updating without a new logo file keeps the existing logo path").
