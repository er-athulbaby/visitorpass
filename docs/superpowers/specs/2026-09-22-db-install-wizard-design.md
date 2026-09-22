# Database & Environment Install Step — Design Spec

Date: 2026-09-22

## Purpose

VisitorPass is distributed as one Laravel install per reselling customer, on
shared/cPanel hosting aimed at non-technical buyers. Today, before the
existing `/setup` wizard (mode selection + admin account) can even render,
the buyer must manually edit `.env` with DB credentials and run
`php artisan migrate` via SSH — a hard blocker for a non-technical buyer on
shared hosting who may not have SSH access at all.

This adds a **new first stage**, `/install`, that collects DB connection
details (and `APP_URL`) through a web form, writes `.env`, and runs
`key:generate` + `migrate` in-process, so the buyer never touches a terminal.
The existing `/setup` wizard (mode + admin creation) is unchanged and runs
immediately after.

## Non-goals

- Not a multi-tenant installer — still one Laravel codebase per customer.
- Does not touch the existing `/setup` wizard, branding settings, or any
  other shipped feature.
- Does not attempt to support hosts where the web server user cannot write
  to the project root (`.env` must be writable) — this is a documented
  requirement, not something the app works around.

## Flow

1. Buyer uploads/extracts the Laravel codebase to shared hosting, points
   their domain at `public/`, and visits the site for the first time.
2. `EnsureInstalled` middleware (global, **prepended**, runs before session
   start) sees no `storage/app/installed` marker, cannot reach the DB using
   whatever is in `.env` (freshly unzipped `.env.example`-derived values),
   and redirects to `/install`.
3. Buyer fills in DB host/port/database/username/password and the site's
   `APP_URL`, submits.
4. `InstallController@store` writes `.env`, re-points the in-process config
   at the new values, tests the connection, generates `APP_KEY` if empty,
   runs migrations, writes the `installed` marker, and redirects to
   `/setup`.
5. The existing `/setup` wizard (mode selection + admin creation) runs
   exactly as it does today.
6. On every subsequent request, `EnsureInstalled` sees the marker and is a
   no-op.

## Components

### `App\Http\Middleware\EnsureInstalled`

Registered as a **prepended** global middleware (`bootstrap/app.php`), so it
runs ahead of session/DB middleware — matching the existing
`EnsureSetupComplete` pattern already used in this codebase for the same
reason.

```
handle(request, next):
    if marker file exists:
        abort(404) if request path is 'install'   // door closed for good, see Security
        return next(request)

    Config::set('session.driver', 'file')   // DB-backed sessions can't work pre-migration

    if request path is 'install' (any method):
        return next(request)

    try connect to DB (DB::connection()->getPdo())
        if connected AND Schema::hasTable('settings'):
            // pre-existing manual .env + migrate install
            write marker file
            return next(request)
        if connected but 'settings' table missing:
            redirect to /install
    catch connection exception:
        redirect to /install
```

The "connects but not migrated" and "can't connect at all" cases both land
on `/install`; the controller's GET handler treats them identically (show
the form, pre-filled from current `.env`).

### `App\Http\Controllers\InstallController`

- `index()` — renders the install form. Reads current `DB_*`/`APP_URL`
  values out of `config()`/`env()` to pre-fill fields (empty/defaults on a
  fresh unzip).
- `store(StoreInstallRequest $request)`:
  1. Validate (see below).
  2. `EnvFileWriter::update([...])` — persists to `.env` on disk.
  3. `Config::set()` the same keys into the current process's
     `database.connections.mysql.*` and `app.url`.
  4. `DB::purge('mysql')`, then attempt `DB::connection()->getPdo()`.
     - On failure: redisplay the form with a flashed error containing the
       driver's exception message (e.g. "Access denied for user..."). Marker
       is never written; `.env` keeps the attempted values so the buyer
       doesn't have to retype everything, only fix the wrong field.
  5. If `config('app.key')` is empty, `Artisan::call('key:generate', ['--force' => true])`.
  6. `Artisan::call('migrate', ['--force' => true])`. If this throws (e.g.
     DB user lacks `CREATE TABLE`), catch it, redisplay the form with the
     error, marker not written.
  7. Write `storage/app/installed` (contents: timestamp, for debugging only
     — presence is all that's checked).
  8. Redirect to `/setup`.

### `App\Services\EnvFileWriter`

Small, dependency-free service — reusable in the controller and unit
testable in isolation.

```php
class EnvFileWriter
{
    public function __construct(private string $path = null)
    {
        $this->path ??= base_path('.env');
    }

    public function update(array $values): void
    {
        $contents = File::get($this->path);
        foreach ($values as $key => $value) {
            $line = $key.'='.$this->quote($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, $line, $contents)
                : $contents."\n".$line;
        }
        File::put($this->path, $contents);
    }

    private function quote(string $value): string
    {
        return preg_match('/[\s#"]/', $value) ? '"'.addslashes($value).'"' : $value;
    }
}
```

### Views

- `resources/views/install/form.blade.php` — a **standalone minimal
  layout**, not extending `layouts.guest.blade.php`. The existing guest/app
  layouts call `Setting::current()` unconditionally for branding, which
  would throw before the DB is connected. Plain Blade/Tailwind form, no
  branding, matching the existing bilingual (EN/AR) support already in the
  app shell only where trivial (locale switch not required here — buyer
  hasn't logged in yet, defaults to app locale).
- `resources/views/errors/db-unreachable.blade.php` — static page shown
  when the marker exists but the DB is unreachable (post-install outage).
  No `Setting::current()` call.

  `EnsureInstalled` stays a zero-overhead pass-through once the marker
  exists — it does not add a new DB round-trip to every request. Instead,
  the existing `EnsureSetupComplete` middleware (which already queries
  `Setting::isComplete()` on every request today) wraps that call in a
  try/catch: on a DB connection exception, it renders
  `errors.db-unreachable` (HTTP 503) instead of letting Laravel's default
  exception page through.

### Validation (`StoreInstallRequest`)

```php
'db_host'     => ['required', 'string', 'max:255'],
'db_port'     => ['required', 'integer', 'between:1,65535'],
'db_database' => ['required', 'string', 'max:64'],
'db_username' => ['required', 'string', 'max:255'],
'db_password' => ['nullable', 'string', 'max:255'],
'app_url'     => ['required', 'url', 'max:255'],
```

## Security

- `/install` is only ever reachable while `storage/app/installed` is
  absent — `EnsureInstalled` refuses to route there once it exists, closing
  the door entirely rather than relying on runtime DB state. This matches
  the earlier decision: a DB outage after install must never resurface a
  credential-entry form to the public.
- The marker is written only after a real, successful `migrate` — not
  merely after a successful connection — so a half-configured install
  (connects but wrong schema permissions) doesn't get locked in.
- `.env` writes happen only for the fixed, validated set of keys above —
  never arbitrary user-supplied keys.

## Error handling

| Failure | Behavior |
|---|---|
| DB unreachable at `EnsureInstalled` check | Redirect to `/install`, form pre-filled from `.env` |
| Bad credentials submitted | Redisplay form with driver error, no marker written |
| `migrate` throws (permissions, etc.) | Redisplay form with the exception message, no marker written |
| DB goes down after install | Static `db-unreachable` page, no `/install` access |
| `.env` not writable by web server | `EnvFileWriter` throws; caught in controller, shown as a form error with a hint to fix file permissions — documented in README as a prerequisite |

## Testing plan

- `EnvFileWriterTest` (unit): add a new key, replace an existing key,
  quote a value containing a space/`#`, leave unrelated lines untouched —
  all against a temp file, no app boot needed.
- `EnsureInstalledTest` (feature):
  - marker present → request passes through untouched.
  - marker absent, sqlite test DB already has `settings` table → marker
    gets written, no redirect.
  - marker absent, fresh unmigrated connection → redirected to `/install`.
- `InstallControllerTest` (feature): happy path against the test SQLite
  connection using a temporary `.env`-like file path injected into
  `EnvFileWriter` (never mutates the real project `.env` during tests) —
  asserts migration ran, marker written, redirect to `/setup`. One test
  forces a connection exception (deliberately broken SQLite path) and
  asserts the form is redisplayed with an error and no marker is written.
- Manual verification: fresh clone, empty-placeholder `.env`, run through
  `/install` against the local MySQL instance, confirm redirect into the
  existing `/setup` wizard and full app afterward.

## Documentation

- README's Installation section is rewritten: `composer install` →
  `npm install && npm run build` → visit the site → fill in `/install` →
  continue through the existing setup wizard. The manual
  `.env` editing / `artisan migrate` steps become a documented **fallback**
  for buyers who do have SSH access and prefer it (the `EnsureInstalled`
  middleware already recognizes a pre-migrated DB and skips `/install`
  automatically in that case).
