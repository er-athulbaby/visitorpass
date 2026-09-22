# Database & Environment Install Wizard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `/install` step that runs before the existing `/setup` wizard, letting a non-technical buyer enter DB credentials and `APP_URL` through a web form instead of hand-editing `.env` and running `artisan migrate` over SSH.

**Architecture:** A prepended global `EnsureInstalled` middleware detects "not installed yet" (no `storage/app/installed` marker) and redirects to `/install`; `InstallController` writes `.env`, re-points the in-process config, runs `key:generate`/`migrate` in-process, and writes the marker on success. The existing `/setup` wizard (mode + admin) is untouched and runs immediately after. A post-install DB outage is handled separately by wrapping the existing `EnsureSetupComplete` check in a try/catch, not by adding a new DB call to every request.

**Tech Stack:** Laravel 13, Pest, MySQL (dev)/SQLite (tests), Blade, no new Composer packages.

**Spec:** [docs/superpowers/specs/2026-09-22-db-install-wizard-design.md](../specs/2026-09-22-db-install-wizard-design.md)

## Global Constraints

- No new Composer or npm dependencies — everything here is plain Laravel (`Artisan::call`, `Config::set`, `DB::purge`, `File` facade).
- `.env` writes touch only the fixed key set: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `APP_URL`, `APP_KEY` — never arbitrary keys.
- The `storage/app/installed` marker is written only after a real, successful `migrate` call — never merely after a successful connection.
- Once the marker exists, `GET`/`POST /install` must 404 — no runtime DB state ever reopens it.
- `EnsureInstalled` must not add a DB round-trip to every request once installed (must be a no-op pass-through in that case).
- All new Blade views (`install/form`, `errors/db-unreachable`) must never call `Setting::current()` or otherwise touch the `settings` table.
- Follow the existing codebase's global-middleware pattern: `$middleware->prepend(...)` in `bootstrap/app.php`, same as `EnsureSetupComplete` already uses.

## Review Focus

- **Buyer submits DB form with correct host but a database that doesn't exist yet** — `migrate` will throw a "database does not exist" style exception; the spec's error-handling table says this must redisplay the form with the driver's message, not a raw 500. Covered in Task 3.
- **Buyer re-visits `/install` in a browser tab after finishing setup on another tab** — marker now exists; spec says this must 404, not silently show/re-run the form. Covered in Task 2.
- **`.env` contains a value needing quoting (e.g. a DB password with a space or `#`)** — `EnvFileWriter` must quote it correctly or the resulting `.env` becomes unparseable for every subsequent boot. Covered in Task 1.
- **A buyer who already did the old manual `.env` + `artisan migrate` dance visits the site for the first time under the new code** — `settings` table already exists; the spec requires this to silently write the marker and proceed, not force them through `/install` too. Covered in Task 2.
- **DB is reachable and migrated, but goes down later (e.g. host restarts MySQL)** — spec requires a plain "contact your host" page, not Laravel's default exception page, and specifically not a route back to `/install`. Covered in Task 4.

---

## File Structure

- `app/Services/EnvFileWriter.php` — new. Reads/writes `.env` key-value pairs. No Laravel facades beyond `File`, so it's trivially unit-testable against a temp file.
- `app/Http/Middleware/EnsureInstalled.php` — new. The "not installed yet" gate described in the spec.
- `app/Http/Controllers/InstallController.php` — new. `index()` (show form) and `store()` (write `.env`, migrate, write marker).
- `app/Http/Requests/StoreInstallRequest.php` — new. Validation rules from the spec.
- `resources/views/install/form.blade.php` — new. Standalone minimal layout (no `Setting::current()`).
- `resources/views/errors/db-unreachable.blade.php` — new. Static page, no `Setting::current()`.
- `app/Http/Middleware/EnsureSetupComplete.php` — modify. Wrap the existing `Setting::isComplete()` call in a try/catch that renders `errors.db-unreachable` (503) on a DB connection exception.
- `bootstrap/app.php` — modify. Register `EnsureInstalled` as a second `prepend()` call, added *after* the existing `EnsureSetupComplete` prepend so it ends up running first.
- `routes/web.php` — modify. Add `GET /install` and `POST /install`.
- `README.md` — modify. Replace the manual `.env` + `artisan migrate` steps with "visit the site, fill in `/install`", keep the manual path documented as a recognized fallback.
- Tests: `tests/Unit/EnvFileWriterTest.php`, `tests/Feature/EnsureInstalledTest.php`, `tests/Feature/InstallWizardTest.php`, `tests/Feature/DbUnreachableTest.php` — all new.

---

### Task 1: `EnvFileWriter` service

**Files:**
- Create: `app/Services/EnvFileWriter.php`
- Test: `tests/Unit/EnvFileWriterTest.php`

**Interfaces:**
- Produces: `App\Services\EnvFileWriter::__construct(?string $path = null)` (defaults to `base_path('.env')`); `update(array $values): void` where `$values` is `['KEY' => 'value', ...]`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Services\EnvFileWriter;

beforeEach(function () {
    $this->path = sys_get_temp_dir().'/env_writer_test_'.uniqid().'.env';
    File::put($this->path, <<<ENV
    APP_NAME=VisitorPass
    DB_HOST=127.0.0.1
    DB_PASSWORD=
    ENV);
});

afterEach(function () {
    if (File::exists($this->path)) {
        File::delete($this->path);
    }
});

test('it replaces an existing key while leaving other lines untouched', function () {
    (new EnvFileWriter($this->path))->update(['DB_HOST' => '10.0.0.5']);

    $contents = File::get($this->path);

    expect($contents)->toContain('DB_HOST=10.0.0.5');
    expect($contents)->toContain('APP_NAME=VisitorPass');
    expect($contents)->not->toContain('DB_HOST=127.0.0.1');
});

test('it adds a key that does not exist yet', function () {
    (new EnvFileWriter($this->path))->update(['DB_PORT' => '3306']);

    expect(File::get($this->path))->toContain('DB_PORT=3306');
});

test('it quotes a value containing a space', function () {
    (new EnvFileWriter($this->path))->update(['APP_NAME' => 'Visitor Pass Pro']);

    expect(File::get($this->path))->toContain('APP_NAME="Visitor Pass Pro"');
});

test('it quotes a value containing a hash and escapes embedded quotes', function () {
    (new EnvFileWriter($this->path))->update(['DB_PASSWORD' => 'p#ss"word']);

    expect(File::get($this->path))->toContain('DB_PASSWORD="p#ss\\"word"');
});

test('it leaves a plain value unquoted', function () {
    (new EnvFileWriter($this->path))->update(['DB_HOST' => '127.0.0.1']);

    expect(File::get($this->path))->toContain('DB_HOST=127.0.0.1');
    expect(File::get($this->path))->not->toContain('"127.0.0.1"');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/EnvFileWriterTest.php`
Expected: FAIL with "Class App\Services\EnvFileWriter not found"

- [ ] **Step 3: Write the implementation**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class EnvFileWriter
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? base_path('.env');
    }

    public function update(array $values): void
    {
        $contents = File::get($this->path);

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, $line, $contents)
                : rtrim($contents)."\n".$line."\n";
        }

        File::put($this->path, $contents);
    }

    private function format(string $value): string
    {
        if (preg_match('/[\s#"]/', $value)) {
            return '"'.addslashes($value).'"';
        }

        return $value;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Unit/EnvFileWriterTest.php`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/EnvFileWriter.php tests/Unit/EnvFileWriterTest.php
git commit -m "feat: add EnvFileWriter service for updating .env values"
```

---

### Task 2: `EnsureInstalled` middleware

**Files:**
- Create: `app/Http/Middleware/EnsureInstalled.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/EnsureInstalledTest.php`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: the middleware class itself, registered globally. Later tasks (3, 4) rely on the marker path `storage/app/installed` (checked via `Storage::disk('local')->exists('installed')`) and on the fact that `/install` is reachable only while that marker is absent.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('a request passes through untouched once the installed marker exists', function () {
    Storage::disk('local')->put('installed', now()->toString());

    $response = $this->get('/nonexistent-route-for-passthrough-check');

    // Reaching Laravel's normal 404 (not a redirect to /install) proves the
    // middleware did not intervene.
    $response->assertNotFound();
});

test('visiting install after the marker exists returns 404', function () {
    Storage::disk('local')->put('installed', now()->toString());

    $this->get('/install')->assertNotFound();
    $this->post('/install', [])->assertNotFound();
});

test('when no marker exists but the settings table is already migrated, the marker is written and no redirect happens', function () {
    expect(Storage::disk('local')->exists('installed'))->toBeFalse();

    $response = $this->get('/setup');

    $response->assertOk();
    expect(Storage::disk('local')->exists('installed'))->toBeTrue();
});

test('when no marker exists and the database is not migrated, the request is redirected to install', function () {
    Schema::dropIfExists('settings');

    $response = $this->get('/setup');

    $response->assertRedirect('/install');
    expect(Storage::disk('local')->exists('installed'))->toBeFalse();
});

test('install form itself is reachable when nothing is installed yet', function () {
    Schema::dropIfExists('settings');

    $this->get('/install')->assertOk();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/EnsureInstalledTest.php`
Expected: FAIL — route `/install` doesn't exist yet (404 for the wrong reason) and no middleware exists to write the marker.

- [ ] **Step 3: Write the middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $isInstallRoute = $request->is('install');

        if (Storage::disk('local')->exists('installed')) {
            abort_if($isInstallRoute, 404);

            return $next($request);
        }

        Config::set('session.driver', 'file');

        if ($isInstallRoute) {
            return $next($request);
        }

        try {
            DB::connection()->getPdo();
            $connected = true;
        } catch (Throwable) {
            $connected = false;
        }

        if ($connected && Schema::hasTable('settings')) {
            Storage::disk('local')->put('installed', now()->toString());

            return $next($request);
        }

        return redirect('/install');
    }
}
```

- [ ] **Step 4: Add a temporary route so the test file's `/install` assertions have something to hit**

Modify `routes/web.php`, add above the existing `/setup` routes:

```php
Route::get('/install', fn () => 'install form placeholder')->name('install.index');
Route::post('/install', fn () => 'install store placeholder')->name('install.store');
```

(Task 3 replaces these two lines with the real controller routes.)

- [ ] **Step 5: Register the middleware in `bootstrap/app.php`**

Modify `bootstrap/app.php`:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\EnsureSetupComplete::class);
        $middleware->prepend(\App\Http\Middleware\EnsureInstalled::class);
```

(`prepend()` inserts at the front of the stack on each call, so the *last* call ends up running first — this puts `EnsureInstalled` ahead of `EnsureSetupComplete`, matching the spec's "before session/DB middleware" requirement.)

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/EnsureInstalledTest.php`
Expected: PASS (5 tests)

- [ ] **Step 7: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS — existing `SetupGateTest` tests still pass because they run against the already-migrated test DB, so `EnsureInstalled` writes the marker on first request and gets out of the way.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Middleware/EnsureInstalled.php bootstrap/app.php routes/web.php tests/Feature/EnsureInstalledTest.php
git commit -m "feat: add EnsureInstalled middleware gating access before the DB is set up"
```

---

### Task 3: Install form, controller, and validation

**Files:**
- Create: `app/Http/Controllers/InstallController.php`
- Create: `app/Http/Requests/StoreInstallRequest.php`
- Create: `resources/views/install/form.blade.php`
- Modify: `routes/web.php` (replace the Task 2 placeholder routes)
- Test: `tests/Feature/InstallWizardTest.php`

**Interfaces:**
- Consumes: `App\Services\EnvFileWriter` (Task 1) — `new EnvFileWriter()->update(array $values): void`. `Storage::disk('local')->exists('installed')` / `->put('installed', ...)` (Task 2's marker convention).
- Produces: routes `install.index` (`GET /install`) and `install.store` (`POST /install`), used by `EnsureInstalled` (already wired in Task 2).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Services\EnvFileWriter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    // Roll back fully (not just drop the table) so the migrations-tracking
    // table forgets it ran too — otherwise a later `artisan migrate` call
    // sees nothing pending and won't recreate anything.
    Artisan::call('migrate:rollback', ['--force' => true]);
    $this->envPath = sys_get_temp_dir().'/install_test_'.uniqid().'.env';
    file_put_contents($this->envPath, "APP_KEY=\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=old\nDB_USERNAME=old\nDB_PASSWORD=\nAPP_URL=http://localhost\n");
    $this->app->instance(EnvFileWriter::class, new EnvFileWriter($this->envPath));
});

afterEach(function () {
    if (file_exists($this->envPath)) {
        unlink($this->envPath);
    }
});

test('the install form renders when nothing is installed yet', function () {
    $this->get('/install')
        ->assertOk()
        ->assertSee('DB Host', false)
        ->assertSee('Database Name', false);
});

test('submitting valid database details writes the env file, migrates, and redirects to setup', function () {
    $response = $this->post('/install', [
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_database' => 'testing',
        'db_username' => 'tester',
        'db_password' => 'secret',
        'app_url' => 'https://example.com',
    ]);

    $response->assertRedirect('/setup');
    expect(Storage::disk('local')->exists('installed'))->toBeTrue();
    expect(Schema::hasTable('settings'))->toBeTrue();
    expect(file_get_contents($this->envPath))->toContain('DB_DATABASE=testing');
    expect(file_get_contents($this->envPath))->toContain('APP_URL=https://example.com');
});

test('validation rejects a missing database name', function () {
    $response = $this->post('/install', [
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_database' => '',
        'db_username' => 'tester',
        'db_password' => '',
        'app_url' => 'https://example.com',
    ]);

    $response->assertSessionHasErrors('db_database');
    expect(Storage::disk('local')->exists('installed'))->toBeFalse();
});

test('a connection failure redisplays the form with an error and does not write the marker', function () {
    DB::shouldReceive('purge')->once()->with('mysql');
    DB::shouldReceive('connection')->andThrow(new \RuntimeException('SQLSTATE[HY000] Connection refused'));

    $response = $this->post('/install', [
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_database' => 'testing',
        'db_username' => 'tester',
        'db_password' => 'secret',
        'app_url' => 'https://example.com',
    ]);

    $response->assertRedirect('/install');
    $response->assertSessionHas('install_error');
    expect(Storage::disk('local')->exists('installed'))->toBeFalse();
});
```

The fourth test mocks the `DB` facade to force a real exception, since the
test suite's actual default connection (SQLite) always succeeds regardless
of the posted values — there's no way to make a genuine connection failure
happen through input alone in this environment. Mocking is safe here
because `EnsureInstalled` doesn't touch `DB` at all when the request path is
`/install` (it returns early), so nothing else in the pipeline needs the
real facade for this request.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/InstallWizardTest.php`
Expected: FAIL — `InstallController` doesn't exist yet.

- [ ] **Step 3: Write the form request**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:64'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInstallRequest;
use App\Services\EnvFileWriter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InstallController extends Controller
{
    public function index()
    {
        return view('install.form', [
            'values' => [
                'db_host' => config('database.connections.mysql.host'),
                'db_port' => config('database.connections.mysql.port'),
                'db_database' => config('database.connections.mysql.database'),
                'db_username' => config('database.connections.mysql.username'),
                'app_url' => config('app.url'),
            ],
        ]);
    }

    public function store(StoreInstallRequest $request, EnvFileWriter $envFileWriter)
    {
        $validated = $request->validated();

        try {
            $envFileWriter->update([
                'DB_HOST' => $validated['db_host'],
                'DB_PORT' => $validated['db_port'],
                'DB_DATABASE' => $validated['db_database'],
                'DB_USERNAME' => $validated['db_username'],
                'DB_PASSWORD' => $validated['db_password'] ?? '',
                'APP_URL' => $validated['app_url'],
            ]);
        } catch (Throwable $e) {
            return redirect('/install')->with('install_error', 'Could not write .env — check file permissions: '.$e->getMessage());
        }

        Config::set([
            'database.connections.mysql.host' => $validated['db_host'],
            'database.connections.mysql.port' => $validated['db_port'],
            'database.connections.mysql.database' => $validated['db_database'],
            'database.connections.mysql.username' => $validated['db_username'],
            'database.connections.mysql.password' => $validated['db_password'] ?? '',
            'app.url' => $validated['app_url'],
        ]);

        try {
            DB::purge('mysql');
            DB::connection()->getPdo();

            if (empty(config('app.key'))) {
                Artisan::call('key:generate', ['--force' => true]);
            }

            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            return redirect('/install')->with('install_error', $e->getMessage());
        }

        Storage::disk('local')->put('installed', now()->toString());

        return redirect('/setup');
    }
}
```

- [ ] **Step 5: Write the view**

```blade
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Install &mdash; VisitorPass</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-100">
        <div class="min-h-screen flex flex-col items-center pt-12">
            <div class="w-full sm:max-w-md px-6 py-4 bg-white shadow-md sm:rounded-lg">
                <h1 class="text-lg font-semibold mb-4">Connect your database</h1>

                @if (session('install_error'))
                    <div class="mb-4 text-sm text-red-600">{{ session('install_error') }}</div>
                @endif

                @if ($errors->any())
                    <ul class="mb-4 text-sm text-red-600 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ url('/install') }}">
                    @csrf

                    <label class="block text-sm font-medium mb-1">DB Host</label>
                    <input type="text" name="db_host" value="{{ old('db_host', $values['db_host']) }}" class="w-full border rounded px-3 py-2 mb-3">

                    <label class="block text-sm font-medium mb-1">DB Port</label>
                    <input type="text" name="db_port" value="{{ old('db_port', $values['db_port']) }}" class="w-full border rounded px-3 py-2 mb-3">

                    <label class="block text-sm font-medium mb-1">Database Name</label>
                    <input type="text" name="db_database" value="{{ old('db_database', $values['db_database']) }}" class="w-full border rounded px-3 py-2 mb-3">

                    <label class="block text-sm font-medium mb-1">DB Username</label>
                    <input type="text" name="db_username" value="{{ old('db_username', $values['db_username']) }}" class="w-full border rounded px-3 py-2 mb-3">

                    <label class="block text-sm font-medium mb-1">DB Password</label>
                    <input type="password" name="db_password" class="w-full border rounded px-3 py-2 mb-3">

                    <label class="block text-sm font-medium mb-1">Site URL</label>
                    <input type="text" name="app_url" value="{{ old('app_url', $values['app_url']) }}" class="w-full border rounded px-3 py-2 mb-4">

                    <button type="submit" class="w-full bg-gray-800 text-white rounded px-3 py-2">Connect &amp; continue</button>
                </form>
            </div>
        </div>
    </body>
</html>
```

- [ ] **Step 6: Replace the placeholder routes**

Modify `routes/web.php`, replacing the two placeholder lines added in Task 2:

```php
Route::get('/install', [InstallController::class, 'index'])->name('install.index');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');
```

Add the import near the other controller `use` statements:

```php
use App\Http\Controllers\InstallController;
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/InstallWizardTest.php`
Expected: PASS (4 tests)

- [ ] **Step 8: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/InstallController.php app/Http/Requests/StoreInstallRequest.php resources/views/install/form.blade.php routes/web.php tests/Feature/InstallWizardTest.php
git commit -m "feat: add install wizard for database setup and migration"
```

---

### Task 4: Post-install DB outage page

**Files:**
- Create: `resources/views/errors/db-unreachable.blade.php`
- Modify: `app/Http/Middleware/EnsureSetupComplete.php`
- Test: `tests/Feature/DbUnreachableTest.php`

**Interfaces:**
- Consumes: nothing new from earlier tasks (uses the existing `Setting::isComplete()` from `app/Models/Setting.php`).
- Produces: nothing consumed by later tasks — this is the last code task.

- [ ] **Step 1: Write the failing test**

```php
<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('a database connection failure shows a plain outage page instead of crashing', function () {
    // Simulate an already-installed site: the marker must exist, otherwise
    // EnsureInstalled intercepts the request first (it also probes the DB
    // when the marker is absent) and redirects to /install before
    // EnsureSetupComplete ever runs.
    Storage::fake('local');
    Storage::disk('local')->put('installed', now()->toString());

    DB::shouldReceive('connection')->andThrow(new \RuntimeException('SQLSTATE[HY000] Connection refused'));

    // Route through /dashboard as a stand-in for "any normal route" — it
    // still resolves through EnsureSetupComplete before hitting auth.
    $response = $this->get('/dashboard');

    $response->assertStatus(503);
    $response->assertSee('unable to reach the database', false);
});
```

Note: `DB::shouldReceive('connection')` mocks the facade broadly enough that
`Setting::isComplete()`'s underlying query throws when the middleware calls
it, without needing a real broken connection. Because the marker exists,
`EnsureInstalled` never calls `DB` itself, so this single mock only affects
`EnsureSetupComplete`'s call.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/DbUnreachableTest.php`
Expected: FAIL — `EnsureSetupComplete` doesn't catch the exception yet, so this either errors out with an uncaught exception or the mock isn't triggered because the marker/`EnsureInstalled` short-circuits first. Confirm the failure and adjust the mock target if `EnsureInstalled`'s own `DB::connection()->getPdo()` call needs a matching expectation (add `->getPdo()` chain expectations if the test errors on an unexpected call).

- [ ] **Step 3: Add the view**

```blade
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Temporarily unavailable</title>
    </head>
    <body style="font-family: sans-serif; text-align: center; padding-top: 4rem;">
        <h1>We're unable to reach the database</h1>
        <p>Please contact your hosting provider or system administrator.</p>
    </body>
</html>
```

- [ ] **Step 4: Update the middleware**

Modify `app/Http/Middleware/EnsureSetupComplete.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $isSetupRoute = $request->is('setup');

        try {
            $isComplete = Setting::isComplete();
        } catch (Throwable) {
            return response()->view('errors.db-unreachable', [], 503);
        }

        if (! $isComplete && ! $isSetupRoute) {
            return redirect('/setup');
        }

        if ($isComplete && $isSetupRoute) {
            return redirect('/login');
        }

        return $next($request);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/DbUnreachableTest.php`
Expected: PASS

- [ ] **Step 6: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS — all prior tests still hit a working DB, so the try/catch's happy path is exercised as before with no behavior change.

- [ ] **Step 7: Commit**

```bash
git add resources/views/errors/db-unreachable.blade.php app/Http/Middleware/EnsureSetupComplete.php tests/Feature/DbUnreachableTest.php
git commit -m "fix: show a plain outage page instead of crashing on DB failure post-install"
```

---

### Task 5: README update

**Files:**
- Modify: `README.md`

**Interfaces:**
- Consumes: nothing (documentation only).
- Produces: nothing (terminal task).

- [ ] **Step 1: Update the Installation section**

Modify `README.md`, replacing the existing Installation section (lines
17–41 as of this plan's writing) with:

```markdown
## Installation

```bash
composer install
npm install && npm run build

cp .env.example .env
```

Then visit the site in a browser. On first load you'll land on a database
setup screen — enter your MySQL host, port, database name, username, and
password, plus the site's public URL. The app writes these into `.env`,
generates the application key, and runs migrations automatically; no SSH
or terminal access is required.

After the database step, the existing setup wizard walks through choosing
Company or Building mode and creating the first admin account.

### Manual setup (alternative)

If you have SSH access and prefer to configure things yourself, edit
`.env` directly and run:

```bash
php artisan key:generate
php artisan migrate
php artisan storage:link
```

The app detects an already-migrated database and skips the database
setup screen automatically.
```

- [ ] **Step 2: Verify the file renders sensibly**

Run: `cat README.md` (or open it) and confirm no broken Markdown (stray
code fences, etc.) around the edited section.

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: document the new install wizard and keep manual setup as a fallback"
```

---

## Final Steps

- [ ] Run `php artisan test` once more for the whole suite.
- [ ] Run `npm run build` to confirm the new Blade views don't break the asset build.
- [ ] Manually verify locally: rename `storage/app/installed` out of the way if present, point `.env` at a fresh empty MySQL database with wrong credentials first (confirm redirect + error), then correct credentials (confirm redirect into `/setup`, and that `/install` 404s afterward).
- [ ] Push to `main` per this project's established workflow (direct commits, no feature branches).
