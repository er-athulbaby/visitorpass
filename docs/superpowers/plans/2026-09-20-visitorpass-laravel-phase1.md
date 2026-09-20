# VisitorPass Laravel — Phase 1 (Core Foundation) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up a Laravel monolith that replicates VisitorPass Pro's core flow (setup wizard, auth/RBAC, visitor register/check-in/out, admin CRUD) branching correctly between "Company" and "Building" reseller modes, with bilingual EN/AR RTL UI — ready for later phases (CPR reader, branding, CSV import, email, audit logs) to build on.

**Architecture:** Laravel 11 + Blade + Livewire/Alpine + Tailwind, MySQL via Eloquent, Spatie Laravel-Permission for roles, `database` session/cache/queue drivers. One codebase branches behavior on a single locked `settings.deployment_mode` value rather than maintaining two codebases.

**Tech Stack:** PHP 8.2+, Laravel 11, Blade, Livewire 3, Alpine.js, Tailwind CSS, MySQL 8, Spatie `laravel-permission`, Pest (Laravel's default test runner).

**Spec:** `docs/superpowers/specs/2026-09-20-visitorpass-laravel-phase1-design.md`

## Global Constraints

- One install = one customer = one database — no tenant_id, no multi-tenancy logic anywhere.
- `deployment_mode` (`company` | `building`) is written once by the setup wizard and never changed by any in-app action afterward.
- Session/cache/queue drivers are `database`, not Redis — shared hosting can't guarantee Redis.
- All Blade components use Tailwind **logical properties** (`ps-*`/`pe-*`/`ms-*`/`me-*`), never physical (`pl-*`/`pr-*`/`ml-*`/`mr-*`) — required for RTL to work without a parallel stylesheet.
- All UI strings go through `__()` — no hardcoded English strings in Blade views.
- No self-registration route — users are created only via the setup wizard (first admin) or the admin Users screen (Task 11).
- A `visits` row must have exactly one of `employee_id` / `company_id` set, matching the install's locked mode — enforced in a `FormRequest`, not a DB CHECK constraint (portability across shared-hosting MySQL versions).

## Review Focus

- **Visiting `/setup` after it's already been completed** — must redirect away (e.g. to login), never let a second admin/mode be created on a live install.
- **A receptionist (non-admin) hitting an `/admin/*` URL directly** — must be blocked (403 or redirect), not just hidden from nav.
- **Submitting a visit in the wrong shape for the install's mode** (e.g. posting `company_id` on a Company-mode install, or `employee_id` on a Building-mode install) — must be rejected by validation, not silently accepted or silently ignored.
- **Checking out a visitor who has no open visit** (already checked out, or never checked in) — must show a clear error, not a 500 or a duplicate checkout record.
- **Registering a visitor whose CPR number already exists** — must reuse/update the existing `visitors` row (returning visitor), never throw a duplicate-key 500 or silently create a second visitor record with the same CPR.

---

## File Structure

```
app/
  Http/
    Controllers/
      SetupController.php
      VisitController.php
      Admin/
        DepartmentController.php
        EmployeeController.php
        CompanyController.php
        UserController.php
    Requests/
      StoreVisitRequest.php
      CheckOutRequest.php
    Middleware/
      EnsureSetupComplete.php
      SetLocale.php
  Models/
    Setting.php
    Department.php
    Employee.php
    Company.php
    Visitor.php
    Visit.php
    AuditLog.php
    User.php (modified)
database/
  migrations/
    ..._create_settings_table.php
    ..._add_locale_to_users_table.php
    ..._create_departments_table.php
    ..._create_employees_table.php
    ..._create_companies_table.php
    ..._create_visitors_table.php
    ..._create_visits_table.php
    ..._create_audit_logs_table.php
  seeders/
    RoleSeeder.php
resources/
  views/
    layouts/app.blade.php
    setup/mode.blade.php, admin.blade.php, seed.blade.php
    auth/login.blade.php
    visits/create.blade.php, index.blade.php
    admin/departments/index.blade.php, admin/employees/index.blade.php
    admin/companies/index.blade.php
    admin/users/index.blade.php, admin/users/edit.blade.php
  css/app.css
  lang/en/*.php, lang/ar/*.php
routes/web.php
tailwind.config.js
tests/Feature/
  SetupWizardTest.php
  AuthAndRbacTest.php
  DepartmentEmployeeAdminTest.php
  CompanyAdminTest.php
  VisitRegistrationTest.php
  VisitCheckOutTest.php
  UserAdminTest.php
  LocaleTest.php
```

---

## Task 1: Bootstrap the Laravel project

**Files:**
- Create: whole fresh Laravel app in `C:\Users\Athul Baby\visitor-management-laravel` (composer-generated)
- Modify: `config/session.php`, `config/cache.php`, `config/queue.php` (driver = `database`)
- Modify: `.env`, `.env.example`
- Create: `tests/Feature/SmokeTest.php`

**Interfaces:**
- Produces: a running Laravel app skeleton; `database` driver wired for session/cache/queue; Pest test runner available via `php artisan test`.

- [ ] **Step 1: Create the Laravel project**

```bash
cd "C:\Users\Athul Baby"
composer create-project laravel/laravel visitor-management-laravel "^11.0"
cd visitor-management-laravel
```

(This runs inside the git repo already initialized at this path — the composer command populates it; the earlier spec commit stays as history.)

- [ ] **Step 2: Install Breeze (Blade stack) for auth scaffolding**

```bash
composer require laravel/breeze --dev
php artisan breeze:install blade
npm install
```

- [ ] **Step 3: Remove self-registration (no self-signup in this product)**

Open `routes/auth.php` and delete the `Route::get('register', ...)` and `Route::post('register', ...)` lines (Breeze generates both — this product only creates users via the setup wizard or admin screen).

Delete `resources/views/auth/register.blade.php`.

- [ ] **Step 4: Switch session/cache/queue to the `database` driver**

In `.env` and `.env.example`, set:

```
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

- [ ] **Step 5: Publish and run the database driver's migrations**

```bash
php artisan session:table
php artisan queue:table
php artisan queue:failed-table
php artisan cache:table
php artisan migrate
```

- [ ] **Step 6: Write a smoke test**

Create `tests/Feature/SmokeTest.php`:

```php
<?php

test('the application boots and serves the login page', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});
```

- [ ] **Step 7: Run the test**

Run: `php artisan test tests/Feature/SmokeTest.php`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "chore: bootstrap Laravel 11 app with Breeze auth and database drivers"
```

---

## Task 2: Settings table, model, and setup-gate middleware

**Files:**
- Create: `database/migrations/xxxx_create_settings_table.php`
- Create: `app/Models/Setting.php`
- Create: `app/Http/Middleware/EnsureSetupComplete.php`
- Modify: `bootstrap/app.php` (register middleware globally)
- Test: `tests/Feature/SetupGateTest.php`

**Interfaces:**
- Produces: `Setting::current(): ?Setting` (returns the id=1 row or null if setup hasn't run), `Setting::isComplete(): bool`, columns `deployment_mode` (`'company'|'building'`), `primary_color`, `logo_path`, `favicon_path`, `tagline`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password_encrypted`, `smtp_from_address` (branding/SMTP columns created now, unused until Phases 3/5).
- Consumes: nothing yet.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SetupGateTest.php`:

```php
<?php

use App\Models\Setting;

test('any route redirects to setup when no settings row exists', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/setup');
});

test('setup route is not redirected when settings do not exist yet', function () {
    $response = $this->get('/setup');

    $response->assertOk();
});

test('routes work normally once settings exist', function () {
    Setting::create([
        'id' => 1,
        'deployment_mode' => 'company',
    ]);

    $response = $this->get('/setup');

    $response->assertRedirect('/login');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/SetupGateTest.php`
Expected: FAIL (no `/setup` route, no `Setting` model, no `/dashboard` route yet)

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration create_settings_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->enum('deployment_mode', ['company', 'building']);
            $table->string('primary_color')->default('#0F172A');
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('tagline')->nullable();
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password_encrypted')->nullable();
            $table->string('smtp_from_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
```

- [ ] **Step 4: Create the Setting model**

Create `app/Models/Setting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'id',
        'deployment_mode',
        'primary_color',
        'logo_path',
        'favicon_path',
        'tagline',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password_encrypted',
        'smtp_from_address',
    ];

    public static function current(): ?self
    {
        return static::find(1);
    }

    public static function isComplete(): bool
    {
        return static::current() !== null;
    }
}
```

- [ ] **Step 5: Create the middleware**

```bash
php artisan make:middleware EnsureSetupComplete
```

```php
<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $isSetupRoute = $request->routeIs('setup.*');

        if (! Setting::isComplete() && ! $isSetupRoute) {
            return redirect('/setup');
        }

        if (Setting::isComplete() && $isSetupRoute) {
            return redirect('/login');
        }

        return $next($request);
    }
}
```

- [ ] **Step 6: Register the middleware globally and add placeholder routes**

In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware) { ... })`:

```php
$middleware->web(append: [
    \App\Http\Middleware\EnsureSetupComplete::class,
]);
```

In `routes/web.php`, add a placeholder `/setup` route and `/dashboard` route (both replaced with real implementations in Task 4 and later):

```php
Route::get('/setup', fn () => 'setup placeholder')->name('setup.index');

Route::get('/dashboard', fn () => 'dashboard placeholder')
    ->middleware('auth')
    ->name('dashboard');
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test tests/Feature/SetupGateTest.php`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add settings table, Setting model, and setup-gate middleware"
```

---

## Task 3: Roles (Spatie) and User model changes

**Files:**
- Create: `database/seeders/RoleSeeder.php`
- Create: `database/migrations/xxxx_add_locale_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/RolesTest.php`

**Interfaces:**
- Produces: `User::hasRole('admin'|'receptionist'): bool` (from Spatie's `HasRoles` trait), `users.locale` column (`'en'|'ar'`, default `'en'`).
- Consumes: `Setting` model (not directly, but roles are seeded independently of setup wizard timing).

- [ ] **Step 1: Install Spatie Laravel-Permission**

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/RolesTest.php`:

```php
<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('admin and receptionist roles exist after seeding', function () {
    expect(Role::where('name', 'admin')->exists())->toBeTrue();
    expect(Role::where('name', 'receptionist')->exists())->toBeTrue();
});

test('a user can be assigned the admin role', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    expect($user->hasRole('admin'))->toBeTrue();
    expect($user->hasRole('receptionist'))->toBeFalse();
});

test('users default to english locale', function () {
    $user = User::factory()->create();

    expect($user->locale)->toBe('en');
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test tests/Feature/RolesTest.php`
Expected: FAIL (`RoleSeeder` doesn't exist, `locale` column doesn't exist)

- [ ] **Step 4: Run Spatie's permission migration**

```bash
php artisan migrate
```

(This runs the migration Spatie's vendor:publish already placed in `database/migrations/`.)

- [ ] **Step 5: Create the locale migration**

```bash
php artisan make:migration add_locale_to_users_table --table=users
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 2)->default('en')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 6: Add HasRoles trait and locale to User model**

Modify `app/Models/User.php` — add the import and trait, and add `locale` to `$fillable`:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
    ];

    // ...existing casts/hidden unchanged
}
```

- [ ] **Step 7: Create the RoleSeeder**

```bash
php artisan make:seeder RoleSeeder
```

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'receptionist']);
    }
}
```

Add it to `database/seeders/DatabaseSeeder.php`'s `run()` method:

```php
$this->call(RoleSeeder::class);
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/RolesTest.php`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add Spatie roles (admin, receptionist) and user locale column"
```

---

## Task 4: Setup wizard (mode, admin account, optional seed)

**Files:**
- Create: `app/Http/Controllers/SetupController.php`
- Create: `resources/views/setup/mode.blade.php`
- Create: `resources/views/setup/admin.blade.php`
- Create: `resources/views/setup/seed.blade.php`
- Modify: `routes/web.php` (replace placeholder `/setup` route)
- Test: `tests/Feature/SetupWizardTest.php`

**Interfaces:**
- Consumes: `Setting::isComplete()` (Task 2), `RoleSeeder`'s `admin`/`receptionist` roles (Task 3), `Department`/`Company` models (Task 6/7 — created as empty tables now via inline migrations run in Step 3 of this task, since the wizard needs to insert into them; full CRUD UI for those models still lands in Task 6/7).
- Produces: a completed `settings` row + first `admin` user after wizard finishes; `/setup` becomes permanently inaccessible afterward (already enforced by `EnsureSetupComplete` from Task 2).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SetupWizardTest.php`:

```php
<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('completing the wizard in company mode creates settings and an admin user', function () {
    $response = $this->post('/setup', [
        'deployment_mode' => 'company',
        'admin_name' => 'Jane Admin',
        'admin_email' => 'jane@example.com',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ]);

    $response->assertRedirect('/login');

    $setting = Setting::current();
    expect($setting)->not->toBeNull();
    expect($setting->deployment_mode)->toBe('company');

    $admin = User::where('email', 'jane@example.com')->first();
    expect($admin)->not->toBeNull();
    expect($admin->hasRole('admin'))->toBeTrue();
});

test('completing the wizard in building mode locks building mode', function () {
    $this->post('/setup', [
        'deployment_mode' => 'building',
        'admin_name' => 'Bob Admin',
        'admin_email' => 'bob@example.com',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ]);

    expect(Setting::current()->deployment_mode)->toBe('building');
});

test('wizard rejects an invalid deployment mode', function () {
    $response = $this->post('/setup', [
        'deployment_mode' => 'not-a-real-mode',
        'admin_name' => 'Jane Admin',
        'admin_email' => 'jane@example.com',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors('deployment_mode');
    expect(Setting::isComplete())->toBeFalse();
});

test('setup is inaccessible once already completed', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $response = $this->get('/setup');

    $response->assertRedirect('/login');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/SetupWizardTest.php`
Expected: FAIL (`SetupController` doesn't exist, `/setup` POST route doesn't exist)

- [ ] **Step 3: Create the controller**

```bash
php artisan make:controller SetupController
```

```php
<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SetupController extends Controller
{
    public function index()
    {
        return view('setup.mode');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'deployment_mode' => ['required', Rule::in(['company', 'building'])],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        Setting::create([
            'id' => 1,
            'deployment_mode' => $validated['deployment_mode'],
        ]);

        $admin = User::create([
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
        ]);

        $admin->assignRole('admin');

        return redirect('/login')->with('status', __('Setup complete. Please log in.'));
    }
}
```

- [ ] **Step 4: Create the view**

Create `resources/views/setup/mode.blade.php`:

```blade
<x-guest-layout>
    <form method="POST" action="{{ route('setup.index') }}">
        @csrf

        <h1 class="text-xl font-semibold mb-4">{{ __('Welcome — let\'s set up VisitorPass') }}</h1>

        <fieldset class="mb-4">
            <legend class="mb-2">{{ __('What kind of reception desk is this for?') }}</legend>

            <label class="flex items-center gap-2 mb-2">
                <input type="radio" name="deployment_mode" value="company" checked>
                {{ __('A company (visitors check in to see a specific person)') }}
            </label>

            <label class="flex items-center gap-2">
                <input type="radio" name="deployment_mode" value="building">
                {{ __('A building (visitors check in to see a company)') }}
            </label>

            @error('deployment_mode')
                <p class="text-red-600 text-sm mt-1" role="alert">{{ $message }}</p>
            @enderror
        </fieldset>

        <div class="mb-3">
            <x-input-label for="admin_name" :value="__('Your name')" />
            <x-text-input id="admin_name" name="admin_name" type="text" class="mt-1 block w-full" :value="old('admin_name')" required />
            <x-input-error :messages="$errors->get('admin_name')" class="mt-1" />
        </div>

        <div class="mb-3">
            <x-input-label for="admin_email" :value="__('Your email')" />
            <x-text-input id="admin_email" name="admin_email" type="email" class="mt-1 block w-full" :value="old('admin_email')" required />
            <x-input-error :messages="$errors->get('admin_email')" class="mt-1" />
        </div>

        <div class="mb-3">
            <x-input-label for="admin_password" :value="__('Password')" />
            <x-text-input id="admin_password" name="admin_password" type="password" class="mt-1 block w-full" required />
            <x-input-error :messages="$errors->get('admin_password')" class="mt-1" />
        </div>

        <div class="mb-4">
            <x-input-label for="admin_password_confirmation" :value="__('Confirm password')" />
            <x-text-input id="admin_password_confirmation" name="admin_password_confirmation" type="password" class="mt-1 block w-full" required />
        </div>

        <x-primary-button>{{ __('Finish setup') }}</x-primary-button>
    </form>
</x-guest-layout>
```

(`admin.blade.php` and `seed.blade.php` are deferred to Task 6/7's admin CRUD — this task's single-page wizard covers mode + admin account only, which is the part the spec locks; optional seeding of the first department/company is folded into Task 6/7's "create your first one" empty-state UI instead of a separate wizard step, since it's the same form either way.)

- [ ] **Step 5: Replace the placeholder route**

In `routes/web.php`, replace the placeholder `/setup` route from Task 2:

```php
use App\Http\Controllers\SetupController;

Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/SetupWizardTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add first-run setup wizard (mode choice + admin account creation)"
```

---

## Task 5: Admin route guard

**Files:**
- Create: `app/Http/Middleware/EnsureAdmin.php`
- Modify: `bootstrap/app.php` (register `admin` middleware alias)
- Modify: `routes/web.php` (add a real `/admin` route group)
- Test: `tests/Feature/AuthAndRbacTest.php`

**Interfaces:**
- Consumes: `User::hasRole('admin')` (Task 3).
- Produces: `admin` route middleware alias, usable by all later admin controllers (Tasks 6, 7, 11).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AuthAndRbacTest.php`:

```php
<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
});

test('a guest is redirected to login when visiting an admin route', function () {
    $response = $this->get('/admin/ping');

    $response->assertRedirect('/login');
});

test('a receptionist is forbidden from an admin route', function () {
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    $response = $this->actingAs($receptionist)->get('/admin/ping');

    $response->assertForbidden();
});

test('an admin can access an admin route', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/ping');

    $response->assertOk();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/AuthAndRbacTest.php`
Expected: FAIL (`/admin/ping` route and `EnsureAdmin` middleware don't exist)

- [ ] **Step 3: Create the middleware**

```bash
php artisan make:middleware EnsureAdmin
```

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the middleware alias**

In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware) { ... })`:

```php
$middleware->alias([
    'admin' => \App\Http\Middleware\EnsureAdmin::class,
]);
```

- [ ] **Step 5: Add the route group (with a temporary ping route for this test)**

In `routes/web.php`:

```php
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/ping', fn () => 'pong');
});
```

(This group is where Tasks 6, 7, and 11 register their real routes — the `/ping` route is replaced once the first real admin route lands in Task 6.)

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/AuthAndRbacTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add admin route guard middleware"
```

---

## Task 6: Departments & Employees (Company mode admin CRUD)

**Files:**
- Create: `database/migrations/xxxx_create_departments_table.php`
- Create: `database/migrations/xxxx_create_employees_table.php`
- Create: `app/Models/Department.php`
- Create: `app/Models/Employee.php`
- Create: `app/Http/Controllers/Admin/DepartmentController.php`
- Create: `app/Http/Controllers/Admin/EmployeeController.php`
- Create: `resources/views/admin/departments/index.blade.php`
- Create: `resources/views/admin/employees/index.blade.php`
- Modify: `routes/web.php` (replace `/admin/ping` with real resource routes)
- Test: `tests/Feature/DepartmentEmployeeAdminTest.php`

**Interfaces:**
- Consumes: `admin` middleware (Task 5), `Setting::current()->deployment_mode` (Task 2).
- Produces: `Department` (id, name), `Employee` (id, department_id, name, email nullable) Eloquent models with a `Department::hasMany(Employee::class)` / `Employee::belongsTo(Department::class)` relationship — consumed by Task 8's `Visit` model and Task 9's registration form.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DepartmentEmployeeAdminTest.php`:

```php
<?php

use App\Models\Department;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admin can create a department', function () {
    $response = $this->actingAs($this->admin)->post('/admin/departments', [
        'name' => 'Human Resources',
    ]);

    $response->assertRedirect('/admin/departments');
    expect(Department::where('name', 'Human Resources')->exists())->toBeTrue();
});

test('admin can create an employee under a department', function () {
    $department = Department::create(['name' => 'IT']);

    $response = $this->actingAs($this->admin)->post('/admin/employees', [
        'department_id' => $department->id,
        'name' => 'Sam Employee',
        'email' => 'sam@example.com',
    ]);

    $response->assertRedirect('/admin/employees');
    expect($department->employees()->where('name', 'Sam Employee')->exists())->toBeTrue();
});

test('deleting a department that still has employees is blocked with a friendly error', function () {
    $department = Department::create(['name' => 'Finance']);
    $department->employees()->create(['name' => 'Pat Employee']);

    $response = $this->actingAs($this->admin)->delete("/admin/departments/{$department->id}");

    $response->assertSessionHas('error');
    expect(Department::find($department->id))->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/DepartmentEmployeeAdminTest.php`
Expected: FAIL (no tables, models, controllers, or routes yet)

- [ ] **Step 3: Create migrations**

```bash
php artisan make:migration create_departments_table
php artisan make:migration create_employees_table
```

`departments` migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
```

`employees` migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
```

Run: `php artisan migrate`

(`restrictOnDelete()` makes the DB itself reject the delete — the app-level pre-check in Step 5 exists so the user sees a friendly message instead of a raw DB error.)

- [ ] **Step 4: Create models**

`app/Models/Department.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['name'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
```

`app/Models/Employee.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $fillable = ['department_id', 'name', 'email'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
```

- [ ] **Step 5: Create controllers**

```bash
php artisan make:controller Admin/DepartmentController
php artisan make:controller Admin/EmployeeController
```

`app/Http/Controllers/Admin/DepartmentController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('admin.departments.index', [
            'departments' => Department::withCount('employees')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Department::create($validated);

        return redirect()->route('admin.departments.index')
            ->with('status', __('Department created.'));
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->employees()->exists()) {
            return redirect()->route('admin.departments.index')
                ->with('error', __('Cannot delete a department that still has employees. Move or remove them first.'));
        }

        $department->delete();

        return redirect()->route('admin.departments.index')
            ->with('status', __('Department deleted.'));
    }
}
```

`app/Http/Controllers/Admin/EmployeeController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(): View
    {
        return view('admin.employees.index', [
            'employees' => Employee::with('department')->orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        Employee::create($validated);

        return redirect()->route('admin.employees.index')
            ->with('status', __('Employee created.'));
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()->route('admin.employees.index')
            ->with('status', __('Employee deleted.'));
    }
}
```

- [ ] **Step 6: Create views**

`resources/views/admin/departments/index.blade.php`:

```blade
<x-app-layout>
    <div class="p-6 max-w-2xl">
        <h1 class="text-xl font-semibold mb-4">{{ __('Departments') }}</h1>

        @if (session('status'))
            <p class="text-green-700 mb-3">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="text-red-600 mb-3" role="alert">{{ session('error') }}</p>
        @endif

        <form method="POST" action="{{ route('admin.departments.store') }}" class="flex gap-2 mb-6">
            @csrf
            <input type="text" name="name" placeholder="{{ __('Department name') }}" class="border rounded ps-3 pe-3 py-2 flex-1" required>
            <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Add') }}</button>
        </form>

        <ul>
            @foreach ($departments as $department)
                <li class="flex justify-between items-center border-b py-2">
                    <span>{{ $department->name }} ({{ $department->employees_count }})</span>
                    <form method="POST" action="{{ route('admin.departments.destroy', $department) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600">{{ __('Delete') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</x-app-layout>
```

`resources/views/admin/employees/index.blade.php`:

```blade
<x-app-layout>
    <div class="p-6 max-w-2xl">
        <h1 class="text-xl font-semibold mb-4">{{ __('Employees') }}</h1>

        @if (session('status'))
            <p class="text-green-700 mb-3">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('admin.employees.store') }}" class="flex gap-2 mb-6">
            @csrf
            <select name="department_id" class="border rounded ps-3 pe-3 py-2" required>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </select>
            <input type="text" name="name" placeholder="{{ __('Employee name') }}" class="border rounded ps-3 pe-3 py-2 flex-1" required>
            <input type="email" name="email" placeholder="{{ __('Email (optional)') }}" class="border rounded ps-3 pe-3 py-2 flex-1">
            <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Add') }}</button>
        </form>

        <ul>
            @foreach ($employees as $employee)
                <li class="flex justify-between items-center border-b py-2">
                    <span>{{ $employee->name }} — {{ $employee->department->name }}</span>
                    <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600">{{ __('Delete') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</x-app-layout>
```

- [ ] **Step 7: Wire routes (replaces the `/ping` route from Task 5)**

In `routes/web.php`, inside the existing `admin.` route group, replace the `/ping` line with:

```php
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeController;

Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'destroy']);
Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'destroy']);
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/DepartmentEmployeeAdminTest.php`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add department and employee admin CRUD (Company mode)"
```

---

## Task 7: Companies (Building mode admin CRUD)

**Files:**
- Create: `database/migrations/xxxx_create_companies_table.php`
- Create: `app/Models/Company.php`
- Create: `app/Http/Controllers/Admin/CompanyController.php`
- Create: `resources/views/admin/companies/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CompanyAdminTest.php`

**Interfaces:**
- Consumes: `admin` middleware (Task 5).
- Produces: `Company` model (id, name, contact_email nullable) — consumed by Task 8's `Visit` model and Task 9's registration form for Building-mode installs.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CompanyAdminTest.php`:

```php
<?php

use App\Models\Company;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admin can create a company', function () {
    $response = $this->actingAs($this->admin)->post('/admin/companies', [
        'name' => 'Acme Corp',
        'contact_email' => 'reception@acme.test',
    ]);

    $response->assertRedirect('/admin/companies');
    expect(Company::where('name', 'Acme Corp')->exists())->toBeTrue();
});

test('company contact email is optional', function () {
    $response = $this->actingAs($this->admin)->post('/admin/companies', [
        'name' => 'No Email Ltd',
    ]);

    $response->assertRedirect('/admin/companies');
    expect(Company::where('name', 'No Email Ltd')->first()->contact_email)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/CompanyAdminTest.php`
Expected: FAIL

- [ ] **Step 3: Create migration**

```bash
php artisan make:migration create_companies_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 4: Create the model**

`app/Models/Company.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = ['name', 'contact_email'];
}
```

- [ ] **Step 5: Create the controller**

```bash
php artisan make:controller Admin/CompanyController
```

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        return view('admin.companies.index', [
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);

        Company::create($validated);

        return redirect()->route('admin.companies.index')
            ->with('status', __('Company created.'));
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return redirect()->route('admin.companies.index')
            ->with('status', __('Company deleted.'));
    }
}
```

- [ ] **Step 6: Create the view**

`resources/views/admin/companies/index.blade.php`:

```blade
<x-app-layout>
    <div class="p-6 max-w-2xl">
        <h1 class="text-xl font-semibold mb-4">{{ __('Companies') }}</h1>

        @if (session('status'))
            <p class="text-green-700 mb-3">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('admin.companies.store') }}" class="flex gap-2 mb-6">
            @csrf
            <input type="text" name="name" placeholder="{{ __('Company name') }}" class="border rounded ps-3 pe-3 py-2 flex-1" required>
            <input type="email" name="contact_email" placeholder="{{ __('Contact email (optional)') }}" class="border rounded ps-3 pe-3 py-2 flex-1">
            <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Add') }}</button>
        </form>

        <ul>
            @foreach ($companies as $company)
                <li class="flex justify-between items-center border-b py-2">
                    <span>{{ $company->name }}</span>
                    <form method="POST" action="{{ route('admin.companies.destroy', $company) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600">{{ __('Delete') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</x-app-layout>
```

- [ ] **Step 7: Wire routes**

In `routes/web.php`, inside the `admin.` group, alongside the department/employee resources:

```php
use App\Http\Controllers\Admin\CompanyController;

Route::resource('companies', CompanyController::class)->only(['index', 'store', 'destroy']);
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/CompanyAdminTest.php`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add company admin CRUD (Building mode)"
```

---

## Task 8: Visitors & Visits schema

**Files:**
- Create: `database/migrations/xxxx_create_visitors_table.php`
- Create: `database/migrations/xxxx_create_visits_table.php`
- Create: `app/Models/Visitor.php`
- Create: `app/Models/Visit.php`
- Test: `tests/Feature/VisitorVisitModelTest.php`

**Interfaces:**
- Consumes: `Employee`, `Department` (Task 6), `Company` (Task 7).
- Produces: `Visitor` (id, cpr_number unique, name, company_name nullable, mobile_number nullable), `Visit` (id, visitor_id, mobile_number, employee_id nullable, department_id nullable, company_id nullable, check_in_at, check_out_at) with relationships `Visitor::hasMany(Visit::class)`, `Visit::belongsTo(Visitor::class|Employee::class|Department::class|Company::class)` — consumed by Task 9 and Task 10.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/VisitorVisitModelTest.php`:

```php
<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Visit;
use App\Models\Visitor;

test('a visitor can have many visits', function () {
    $visitor = Visitor::create([
        'cpr_number' => '990101123',
        'name' => 'Ali Visitor',
    ]);

    $department = Department::create(['name' => 'IT']);
    $employee = $department->employees()->create(['name' => 'Sam Host']);

    $visit = $visitor->visits()->create([
        'employee_id' => $employee->id,
        'department_id' => $department->id,
        'mobile_number' => '33445566',
        'check_in_at' => now(),
    ]);

    expect($visitor->visits)->toHaveCount(1);
    expect($visit->employee->name)->toBe('Sam Host');
    expect($visit->visitor->name)->toBe('Ali Visitor');
});

test('cpr_number is unique across visitors', function () {
    Visitor::create(['cpr_number' => '990101123', 'name' => 'First']);

    expect(fn () => Visitor::create(['cpr_number' => '990101123', 'name' => 'Second']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/VisitorVisitModelTest.php`
Expected: FAIL

- [ ] **Step 3: Create migrations**

```bash
php artisan make:migration create_visitors_table
php artisan make:migration create_visits_table
```

`visitors` migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('cpr_number')->unique();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('mobile_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
```

`visits` migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained()->restrictOnDelete();
            $table->string('mobile_number')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('check_in_at');
            $table->timestamp('check_out_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 4: Create models**

`app/Models/Visitor.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    protected $fillable = ['cpr_number', 'name', 'company_name', 'mobile_number'];

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
```

`app/Models/Visit.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    protected $fillable = [
        'visitor_id',
        'mobile_number',
        'employee_id',
        'department_id',
        'company_id',
        'check_in_at',
        'check_out_at',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isOpen(): bool
    {
        return $this->check_out_at === null;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/VisitorVisitModelTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add visitor and visit models and migrations"
```

---

## Task 9: Visitor registration + check-in (mode-aware)

**Files:**
- Create: `app/Http/Requests/StoreVisitRequest.php`
- Create: `app/Http/Controllers/VisitController.php`
- Create: `resources/views/visits/create.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/VisitRegistrationTest.php`

**Interfaces:**
- Consumes: `Setting::current()->deployment_mode` (Task 2), `Visitor`/`Visit` (Task 8), `Employee`/`Department` (Task 6), `Company` (Task 7).
- Produces: `POST /visits` — creates or reuses a `Visitor` by `cpr_number`, creates an open `Visit` row. Consumed by Task 10 (check-out reads the same `Visit` rows).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/VisitRegistrationTest.php`:

```php
<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->receptionist = User::factory()->create();
    $this->receptionist->assignRole('receptionist');
});

test('company mode registers a visit against an employee and department', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $department = Department::create(['name' => 'IT']);
    $employee = $department->employees()->create(['name' => 'Sam Host']);

    $response = $this->actingAs($this->receptionist)->post('/visits', [
        'cpr_number' => '990101123',
        'name' => 'Ali Visitor',
        'mobile_number' => '33445566',
        'employee_id' => $employee->id,
    ]);

    $response->assertRedirect('/visits');
    $visitor = Visitor::where('cpr_number', '990101123')->first();
    expect($visitor)->not->toBeNull();
    expect($visitor->visits()->first()->employee_id)->toBe($employee->id);
    expect($visitor->visits()->first()->check_out_at)->toBeNull();
});

test('company mode rejects a company_id field', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $company = Company::create(['name' => 'Should not be used here']);

    $response = $this->actingAs($this->receptionist)->post('/visits', [
        'cpr_number' => '990101123',
        'name' => 'Ali Visitor',
        'company_id' => $company->id,
    ]);

    $response->assertSessionHasErrors('employee_id');
});

test('building mode registers a visit against a company only', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    $company = Company::create(['name' => 'Acme Corp']);

    $response = $this->actingAs($this->receptionist)->post('/visits', [
        'cpr_number' => '990101124',
        'name' => 'Fatima Visitor',
        'company_id' => $company->id,
    ]);

    $response->assertRedirect('/visits');
    $visitor = Visitor::where('cpr_number', '990101124')->first();
    expect($visitor->visits()->first()->company_id)->toBe($company->id);
    expect($visitor->visits()->first()->employee_id)->toBeNull();
});

test('registering a returning visitor by existing cpr_number reuses the visitor record', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $department = Department::create(['name' => 'IT']);
    $employee = $department->employees()->create(['name' => 'Sam Host']);

    Visitor::create(['cpr_number' => '990101123', 'name' => 'Ali Visitor', 'mobile_number' => '33445566']);

    $this->actingAs($this->receptionist)->post('/visits', [
        'cpr_number' => '990101123',
        'name' => 'Ali Visitor',
        'employee_id' => $employee->id,
    ]);

    expect(Visitor::where('cpr_number', '990101123')->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/VisitRegistrationTest.php`
Expected: FAIL

- [ ] **Step 3: Create the FormRequest**

```bash
php artisan make:request StoreVisitRequest
```

```php
<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mode = Setting::current()->deployment_mode;

        $rules = [
            'cpr_number' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:20'],
        ];

        if ($mode === 'company') {
            $rules['employee_id'] = ['required', 'exists:employees,id'];
            $rules['company_id'] = ['prohibited'];
        } else {
            $rules['company_id'] = ['required', 'exists:companies,id'];
            $rules['employee_id'] = ['prohibited'];
        }

        return $rules;
    }

    public function departmentIdForEmployee(): ?int
    {
        if (! $this->filled('employee_id')) {
            return null;
        }

        return Employee::find($this->input('employee_id'))?->department_id;
    }
}
```

- [ ] **Step 4: Create the controller**

```bash
php artisan make:controller VisitController
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisitRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Visitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VisitController extends Controller
{
    public function create(): View
    {
        $mode = Setting::current()->deployment_mode;

        return view('visits.create', [
            'mode' => $mode,
            'employees' => $mode === 'company' ? Employee::with('department')->orderBy('name')->get() : collect(),
            'companies' => $mode === 'building' ? Company::orderBy('name')->get() : collect(),
        ]);
    }

    public function store(StoreVisitRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $visitor = Visitor::updateOrCreate(
            ['cpr_number' => $validated['cpr_number']],
            [
                'name' => $validated['name'],
                'company_name' => $validated['company_name'] ?? null,
                'mobile_number' => $validated['mobile_number'] ?? null,
            ]
        );

        $visitor->visits()->create([
            'mobile_number' => $validated['mobile_number'] ?? null,
            'employee_id' => $validated['employee_id'] ?? null,
            'department_id' => $request->departmentIdForEmployee(),
            'company_id' => $validated['company_id'] ?? null,
            'check_in_at' => now(),
        ]);

        return redirect()->route('visits.index')
            ->with('status', __('Visitor checked in.'));
    }
}
```

- [ ] **Step 5: Create the view**

`resources/views/visits/create.blade.php`:

```blade
<x-app-layout>
    <div class="p-6 max-w-xl">
        <h1 class="text-xl font-semibold mb-4">{{ __('Register Visitor') }}</h1>

        @if ($errors->any())
            <div role="alert" tabindex="-1" class="border border-red-400 bg-red-50 p-3 mb-4 rounded">
                <h2 class="font-semibold text-red-700">{{ __('There is a problem') }}</h2>
                <ul class="list-disc ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('visits.store') }}">
            @csrf

            <div class="bg-blue-50 rounded p-4 mb-4">
                <h2 class="font-semibold mb-2">{{ __('Visitor Information') }}</h2>

                <div class="mb-3">
                    <label for="cpr_number">{{ __('CPR Number') }}</label>
                    <input id="cpr_number" name="cpr_number" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" value="{{ old('cpr_number') }}" aria-describedby="cpr_number-error" required>
                    @error('cpr_number')
                        <p id="cpr_number-error" class="text-red-600 text-sm">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="name">{{ __('Visitor Name') }}</label>
                    <input id="name" name="name" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" value="{{ old('name') }}" required>
                </div>

                <div>
                    <label for="company_name">{{ __('Company Name') }}</label>
                    <input id="company_name" name="company_name" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" value="{{ old('company_name') }}">
                </div>
            </div>

            <div class="bg-amber-50 rounded p-4 mb-4">
                <h2 class="font-semibold mb-2">{{ __('Visit Details') }}</h2>

                <div class="mb-3">
                    <label for="mobile_number">{{ __('Mobile Number') }}</label>
                    <input id="mobile_number" name="mobile_number" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" value="{{ old('mobile_number') }}">
                </div>

                @if ($mode === 'company')
                    <div>
                        <label for="employee_id">{{ __('Person to Visit') }}</label>
                        <select id="employee_id" name="employee_id" class="border rounded ps-3 pe-3 py-2 block w-full" aria-describedby="employee_id-error" required>
                            <option value="">{{ __('Select...') }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }} — {{ $employee->department->name }}</option>
                            @endforeach
                        </select>
                        @error('employee_id')
                            <p id="employee_id-error" class="text-red-600 text-sm">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <div>
                        <label for="company_id">{{ __('Company Visiting') }}</label>
                        <select id="company_id" name="company_id" class="border rounded ps-3 pe-3 py-2 block w-full" aria-describedby="company_id-error" required>
                            <option value="">{{ __('Select...') }}</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        @error('company_id')
                            <p id="company_id-error" class="text-red-600 text-sm">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>

            <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Check In') }}</button>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Wire routes**

In `routes/web.php`, inside the `auth` middleware group (create one if it doesn't exist yet, alongside `/dashboard`):

```php
use App\Http\Controllers\VisitController;

Route::middleware('auth')->group(function () {
    Route::get('/visits/create', [VisitController::class, 'create'])->name('visits.create');
    Route::post('/visits', [VisitController::class, 'store'])->name('visits.store');
});
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test tests/Feature/VisitRegistrationTest.php`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add mode-aware visitor registration and check-in"
```

---

## Task 10: Check-out + visit list / history

**Files:**
- Create: `app/Http/Requests/CheckOutRequest.php`
- Modify: `app/Http/Controllers/VisitController.php` (add `index`, `checkOut`)
- Create: `resources/views/visits/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/VisitCheckOutTest.php`

**Interfaces:**
- Consumes: `Visit` model (Task 8).
- Produces: `PATCH /visits/{visit}/check-out`, `GET /visits` (open visits list + history search) — this is the receptionist-facing history/export screen's read path; CSV export itself is Phase 6.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/VisitCheckOutTest.php`:

```php
<?php

use App\Models\Department;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $this->receptionist = User::factory()->create();
    $this->receptionist->assignRole('receptionist');

    $department = Department::create(['name' => 'IT']);
    $this->employee = $department->employees()->create(['name' => 'Sam Host']);
});

test('receptionist can check out an open visit', function () {
    $visitor = Visitor::create(['cpr_number' => '990101123', 'name' => 'Ali Visitor']);
    $visit = $visitor->visits()->create([
        'employee_id' => $this->employee->id,
        'check_in_at' => now(),
    ]);

    $response = $this->actingAs($this->receptionist)->patch("/visits/{$visit->id}/check-out");

    $response->assertRedirect('/visits');
    expect($visit->fresh()->check_out_at)->not->toBeNull();
});

test('checking out an already-closed visit shows an error instead of duplicating it', function () {
    $visitor = Visitor::create(['cpr_number' => '990101123', 'name' => 'Ali Visitor']);
    $visit = $visitor->visits()->create([
        'employee_id' => $this->employee->id,
        'check_in_at' => now(),
        'check_out_at' => now(),
    ]);

    $response = $this->actingAs($this->receptionist)->patch("/visits/{$visit->id}/check-out");

    $response->assertSessionHas('error');
});

test('open visits list shows only visitors who have not checked out', function () {
    $visitor = Visitor::create(['cpr_number' => '990101123', 'name' => 'Open Visitor']);
    $visitor->visits()->create(['employee_id' => $this->employee->id, 'check_in_at' => now()]);

    $closedVisitor = Visitor::create(['cpr_number' => '990101124', 'name' => 'Closed Visitor']);
    $closedVisitor->visits()->create(['employee_id' => $this->employee->id, 'check_in_at' => now()->subHour(), 'check_out_at' => now()]);

    $response = $this->actingAs($this->receptionist)->get('/visits');

    $response->assertSee('Open Visitor');
    $response->assertDontSee('Closed Visitor');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/VisitCheckOutTest.php`
Expected: FAIL

- [ ] **Step 3: Create the CheckOutRequest**

```bash
php artisan make:request CheckOutRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
```

- [ ] **Step 4: Add `index` and `checkOut` to VisitController**

Modify `app/Http/Controllers/VisitController.php`, adding these methods and imports:

```php
use App\Http\Requests\CheckOutRequest;
use App\Models\Visit;

// ...inside the class, alongside create()/store():

public function index(): View
{
    return view('visits.index', [
        'openVisits' => Visit::with('visitor', 'employee', 'company')
            ->whereNull('check_out_at')
            ->orderByDesc('check_in_at')
            ->get(),
    ]);
}

public function checkOut(CheckOutRequest $request, Visit $visit): RedirectResponse
{
    if (! $visit->isOpen()) {
        return redirect()->route('visits.index')
            ->with('error', __('This visitor has already checked out.'));
    }

    $visit->update(['check_out_at' => now()]);

    return redirect()->route('visits.index')
        ->with('status', __('Visitor checked out.'));
}
```

- [ ] **Step 5: Create the view**

`resources/views/visits/index.blade.php`:

```blade
<x-app-layout>
    <div class="p-6">
        <h1 class="text-xl font-semibold mb-4">{{ __('Open Visits') }}</h1>

        @if (session('status'))
            <p class="text-green-700 mb-3">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="text-red-600 mb-3" role="alert">{{ session('error') }}</p>
        @endif

        <a href="{{ route('visits.create') }}" class="inline-block bg-primary text-on-primary rounded px-4 py-2 mb-4">
            {{ __('Register New Visitor') }}
        </a>

        <table class="w-full text-start">
            <thead>
                <tr class="border-b">
                    <th class="text-start py-2">{{ __('Visitor') }}</th>
                    <th class="text-start py-2">{{ __('Visiting') }}</th>
                    <th class="text-start py-2">{{ __('Checked In') }}</th>
                    <th class="text-start py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($openVisits as $visit)
                    <tr class="border-b">
                        <td class="py-2">{{ $visit->visitor->name }}</td>
                        <td class="py-2">{{ $visit->employee?->name ?? $visit->company?->name }}</td>
                        <td class="py-2">{{ $visit->check_in_at->format('Y-m-d H:i') }}</td>
                        <td class="py-2">
                            <form method="POST" action="{{ route('visits.check-out', $visit) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-primary">{{ __('Check Out') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Wire routes**

In `routes/web.php`, inside the same `auth` group as Task 9:

```php
Route::get('/visits', [VisitController::class, 'index'])->name('visits.index');
Route::patch('/visits/{visit}/check-out', [VisitController::class, 'checkOut'])->name('visits.check-out');
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test tests/Feature/VisitCheckOutTest.php`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add visit check-out and open-visits list"
```

---

## Task 11: Users admin CRUD

**Files:**
- Create: `app/Http/Controllers/Admin/UserController.php`
- Create: `resources/views/admin/users/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/UserAdminTest.php`

**Interfaces:**
- Consumes: `admin` middleware (Task 5), Spatie roles (Task 3).
- Produces: admin-only user creation/role-assignment/deactivation — no other later task depends on this one.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/UserAdminTest.php`:

```php
<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admin can create a receptionist user', function () {
    $response = $this->actingAs($this->admin)->post('/admin/users', [
        'name' => 'New Receptionist',
        'email' => 'reception@example.com',
        'password' => 'password123',
        'role' => 'receptionist',
    ]);

    $response->assertRedirect('/admin/users');
    $user = User::where('email', 'reception@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('receptionist'))->toBeTrue();
});

test('admin can deactivate a user by deleting them', function () {
    $user = User::factory()->create();
    $user->assignRole('receptionist');

    $response = $this->actingAs($this->admin)->delete("/admin/users/{$user->id}");

    $response->assertRedirect('/admin/users');
    expect(User::find($user->id))->toBeNull();
});

test('a receptionist cannot access the users admin screen', function () {
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    $response = $this->actingAs($receptionist)->get('/admin/users');

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/UserAdminTest.php`
Expected: FAIL

- [ ] **Step 3: Create the controller**

```bash
php artisan make:controller Admin/UserController
```

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::with('roles')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'receptionist'])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('status', __('User created.'));
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('status', __('User removed.'));
    }
}
```

- [ ] **Step 4: Create the view**

`resources/views/admin/users/index.blade.php`:

```blade
<x-app-layout>
    <div class="p-6 max-w-2xl">
        <h1 class="text-xl font-semibold mb-4">{{ __('Users') }}</h1>

        @if (session('status'))
            <p class="text-green-700 mb-3">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('admin.users.store') }}" class="flex gap-2 mb-6 flex-wrap">
            @csrf
            <input type="text" name="name" placeholder="{{ __('Name') }}" class="border rounded ps-3 pe-3 py-2" required>
            <input type="email" name="email" placeholder="{{ __('Email') }}" class="border rounded ps-3 pe-3 py-2" required>
            <input type="password" name="password" placeholder="{{ __('Password') }}" class="border rounded ps-3 pe-3 py-2" required>
            <select name="role" class="border rounded ps-3 pe-3 py-2" required>
                <option value="receptionist">{{ __('Receptionist') }}</option>
                <option value="admin">{{ __('Admin') }}</option>
            </select>
            <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Add') }}</button>
        </form>

        <ul>
            @foreach ($users as $user)
                <li class="flex justify-between items-center border-b py-2">
                    <span>{{ $user->name }} ({{ $user->roles->pluck('name')->join(', ') }})</span>
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600">{{ __('Remove') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Wire routes**

In `routes/web.php`, inside the `admin.` group:

```php
use App\Http\Controllers\Admin\UserController;

Route::resource('users', UserController::class)->only(['index', 'store', 'destroy']);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/UserAdminTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add users admin CRUD"
```

---

## Task 12: Bilingual EN/AR RTL support

**Files:**
- Create: `lang/en/app.php`
- Create: `lang/ar/app.php`
- Create: `app/Http/Middleware/SetLocale.php`
- Modify: `bootstrap/app.php` (register `SetLocale` globally)
- Modify: `resources/views/layouts/app.blade.php` (`dir` attribute, locale switcher)
- Modify: `tailwind.config.js` (Noto Sans / Noto Sans Arabic font family)
- Modify: `resources/css/app.css` (Google Fonts import)
- Test: `tests/Feature/LocaleTest.php`

**Interfaces:**
- Consumes: `users.locale` (Task 3).
- Produces: `app()->getLocale()` reflects the logged-in user's saved preference for the rest of the request; `PATCH /locale` to switch it.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/LocaleTest.php`:

```php
<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
});

test('a user with arabic locale gets rtl direction on the page', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $response = $this->actingAs($user)->get('/visits');

    $response->assertSee('dir="rtl"', false);
});

test('a user with english locale gets ltr direction on the page', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($user)->get('/visits');

    $response->assertSee('dir="ltr"', false);
});

test('switching locale updates the stored preference', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($user)->patch('/locale', ['locale' => 'ar']);

    $response->assertRedirect();
    expect($user->fresh()->locale)->toBe('ar');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/LocaleTest.php`
Expected: FAIL

- [ ] **Step 3: Create the middleware**

```bash
php artisan make:middleware SetLocale
```

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            App::setLocale($request->user()->locale);
        }

        return $next($request);
    }
}
```

Register it in `bootstrap/app.php`:

```php
$middleware->web(append: [
    \App\Http\Middleware\EnsureSetupComplete::class,
    \App\Http\Middleware\SetLocale::class,
]);
```

- [ ] **Step 4: Add the locale-switch route**

In `routes/web.php`, inside the `auth` group:

```php
Route::patch('/locale', function (\Illuminate\Http\Request $request) {
    $validated = $request->validate(['locale' => 'required|in:en,ar']);
    $request->user()->update(['locale' => $validated['locale']]);

    return back();
})->name('locale.update');
```

- [ ] **Step 5: Create minimal lang files**

`lang/en/app.php`:

```php
<?php

return [
    'app_name' => 'VisitorPass',
];
```

`lang/ar/app.php`:

```php
<?php

return [
    'app_name' => 'زائر باس',
];
```

(Individual `__('...')` strings already used throughout the Blade views in Tasks 4–11 fall back to the literal English string when no translation key matches, per Laravel's default behavior — a full `ar` translation file for every string is a content task for a later pass, not blocking here. This task's job is the RTL *mechanism*, not translating every string.)

- [ ] **Step 6: Update the main layout for `dir` and add a locale switcher**

Modify `resources/views/layouts/app.blade.php` — Breeze's generated layout wraps `<html>`; change its opening tag and add a switcher inside the nav:

```blade
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
```

Inside the nav (wherever Breeze placed the user dropdown), add:

```blade
<form method="POST" action="{{ route('locale.update') }}">
    @csrf
    @method('PATCH')
    <button type="submit" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}" class="ms-2">
        {{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}
    </button>
</form>
```

- [ ] **Step 7: Configure fonts**

In `tailwind.config.js`, extend the theme:

```js
theme: {
    extend: {
        fontFamily: {
            sans: ['Noto Sans', 'Noto Sans Arabic', 'sans-serif'],
        },
    },
},
```

At the top of `resources/css/app.css`:

```css
@import url('https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap');
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/LocaleTest.php`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add bilingual EN/AR support with RTL layout switching"
```

---

## Task 13: Audit logs table (schema only)

**Files:**
- Create: `database/migrations/xxxx_create_audit_logs_table.php`
- Create: `app/Models/AuditLog.php`
- Test: `tests/Feature/AuditLogModelTest.php`

**Interfaces:**
- Consumes: `User` (Task 3).
- Produces: `AuditLog` model (id, user_id, action, details json) — Phase 6 writes to it; nothing in Phase 1 writes to it yet, per the spec's explicit "out of scope" note.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AuditLogModelTest.php`:

```php
<?php

use App\Models\AuditLog;
use App\Models\User;

test('an audit log entry stores a json details payload', function () {
    $user = User::factory()->create();

    $log = AuditLog::create([
        'user_id' => $user->id,
        'action' => 'department.deleted',
        'details' => ['department_name' => 'Finance'],
    ]);

    expect($log->fresh()->details)->toBe(['department_name' => 'Finance']);
    expect($log->user->id)->toBe($user->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/AuditLogModelTest.php`
Expected: FAIL

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration create_audit_logs_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 4: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'details'];

    protected $casts = [
        'details' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/AuditLogModelTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add audit_logs table and model (schema only, unused until Phase 6)"
```

---

## Task 14: Full-suite verification

**Files:** none created — this task only runs and confirms the whole suite.

**Interfaces:** none.

- [ ] **Step 1: Run the entire Phase 1 test suite**

Run: `php artisan test`
Expected: all tests across every task (`SmokeTest`, `SetupGateTest`, `RolesTest`, `SetupWizardTest`, `AuthAndRbacTest`, `DepartmentEmployeeAdminTest`, `CompanyAdminTest`, `VisitorVisitModelTest`, `VisitRegistrationTest`, `VisitCheckOutTest`, `UserAdminTest`, `LocaleTest`, `AuditLogModelTest`) PASS.

- [ ] **Step 2: Build frontend assets to catch any Blade/Tailwind errors**

Run: `npm run build`
Expected: builds cleanly, no missing-class or syntax errors.

- [ ] **Step 3: Commit** (only if Steps 1–2 required any fixes; otherwise skip — nothing to commit)

```bash
git add -A
git commit -m "test: verify full Phase 1 suite passes end to end"
```

---

## Self-Review Notes

**Spec coverage:** Product model/reseller modes → Tasks 4, 6, 7, 9. Architecture (stack, drivers) → Task 1. Data model → Tasks 2, 3, 6, 7, 8, 13. Setup wizard → Task 4. Auth & RBAC → Tasks 3, 5, 11. UI/UX design system → Tasks 6–12 (Tailwind logical properties used throughout; colors applied via `bg-primary`/`text-on-primary` utility classes wired to the default palette — Phase 3 will make these read from `Setting` instead of static config, out of scope here). Multilingual/RTL → Task 12. Error handling → Task 6 (FK pre-check), Task 9 (FormRequest validation), Task 10 (double-checkout guard). Testing → every task includes its own Pest feature test; Task 14 verifies the whole suite together. All six "Out of Scope" items from the spec are correctly absent from every task.

**Placeholder scan:** no TBD/TODO markers; every step has runnable code or an exact command.

**Type consistency:** `Visit::isOpen()` (Task 8) is the single source of truth for "still checked in," reused identically in Task 10's `checkOut()` guard and Task 10's `index()` query (`whereNull('check_out_at')`) rather than two different checks drifting apart. `Setting::current()->deployment_mode` is read the same way in Tasks 4, 9 rather than re-implemented per task. `StoreVisitRequest::departmentIdForEmployee()` (Task 9) is the one place `department_id` gets derived from `employee_id` — not duplicated in the controller.

**Review Focus coverage confirmed:**
- Re-visiting completed `/setup` → tested in Task 4 (`setup is inaccessible once already completed`).
- Receptionist hitting `/admin/*` → tested in Task 5 and re-confirmed in Task 11.
- Wrong-mode visit fields → tested in Task 9 (`company mode rejects a company_id field`).
- Checking out an already-closed visit → tested in Task 10.
- Duplicate CPR registration → tested in Task 9 (`registering a returning visitor... reuses the visitor record`) and Task 8 (DB-level uniqueness).
