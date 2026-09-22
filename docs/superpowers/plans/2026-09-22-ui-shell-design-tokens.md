# UI Overhaul Sub-Project 1: App Shell & Design Tokens Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Breeze default top-nav layout with a persistent sidebar (desktop) / bottom tab bar (mobile) shell, carrying over the React app's already-approved design tokens, with zero changes to any existing page's business logic.

**Architecture:** A new `App\View\Components\SidebarLayout` class (mirroring the existing `AppLayout`/`GuestLayout` pattern already in this codebase) renders a new `layouts/sidebar.blade.php` view, which includes a new `layouts/sidebar-nav.blade.php` partial twice (desktop sidebar, mobile bottom bar) via a `$variant` prop. That nav partial links to five destinations by route name, including two — `dashboard` and `history.index` — that must already resolve before the nav partial is ever rendered anywhere; the two stub pages are therefore built *before* the layout cutover, not after. Every existing page then swaps `<x-app-layout>` for `<x-sidebar-layout>` in one pass; the old layout files and component class are deleted once nothing references them.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS (design tokens only — no new JS/build tooling), Google Material Symbols webfont.

**Spec:** [docs/superpowers/specs/2026-09-22-ui-shell-design-tokens-design.md](../specs/2026-09-22-ui-shell-design-tokens-design.md)

## Global Constraints

- No business logic, backend queries, or form behavior changes on any existing page — only the layout wrapper tag changes (spec Non-goals).
- No new JS build tooling — icons come from a webfont `<link>` tag and a `<span>`, matching the React app's own approach.
- The existing `fontFamily.sans` (`Noto Sans`/`Noto Sans Arabic`) in `tailwind.config.js` must be preserved exactly — it's what makes Arabic (RTL) text render correctly today; the React app's `Inter`-only font stack is not applicable here and must not replace it.
- The existing `colors.primary`/`colors.'on-primary'` entries (`var(--color-primary)`, `var(--color-on-primary)`, no CSS-level fallback) must be preserved exactly — Phase 3's branding system already provides its own default via the `Setting` model, and introducing a competing hardcoded CSS fallback would contradict it.
- `layouts/app.blade.php`, `layouts/navigation.blade.php`, and `app/View/Components/AppLayout.php` are deleted once no view references them — the point being that any missed `<x-app-layout>` reference then fails loudly (view/class not found) instead of silently rendering a stale shell.
- The sidebar nav partial links to `dashboard` and `history.index` by route name on every single render (it's included on every authenticated page). Both routes must already exist and resolve before the nav partial is introduced anywhere — this is why the two stub pages are built first in this plan, not last.

## Review Focus

- **Non-admin (receptionist) user** — sidebar must show only Home/Scan/Visitors/History, no Admin section at all, matching today's `@role('admin')` gating. Covered in Task 4.
- **Building-mode admin** — the Admin group must show "Companies," not "Departments"/"Employees," matching today's `deployment_mode` branch in `layouts/navigation.blade.php`. Covered in Task 4.
- **A page still using the old layout after the cutover** — since `AppLayout.php`/`layouts/app.blade.php` are deleted in this same plan, any missed `<x-app-layout>` reference must fail immediately and obviously (a rendering exception), not silently degrade. Covered by Task 4's regression tests exercising every one of the 9 known consumers.
- **The `$header` slot** — two existing views (`dashboard.blade.php`, `profile/edit.blade.php`) pass a named `header` slot into the layout; the new sidebar layout must keep rendering it exactly as `layouts/app.blade.php` does today, or those two pages silently lose their page title. Covered in Task 4.
- **Dashboard route becomes a controller** — the existing `/dashboard` route is a closure; converting it to `DashboardController@index` must not change its URL, name (`dashboard`), or middleware (`auth`), since other code will reference `route('dashboard')` (Task 4's nav partial, and the mobile header's logo link). Covered in Task 2.

---

## File Structure

- `tailwind.config.js` — modify. Add the React app's `theme.extend` tokens (colors, spacing, fontSize, borderRadius) alongside the two existing, untouched entries (`fontFamily.sans`, `colors.primary`/`'on-primary'`).
- `resources/views/components/icon.blade.php` — new. Thin wrapper around the Material Symbols webfont span.
- `app/Http/Controllers/DashboardController.php` — new. Replaces the closure route.
- `app/Http/Controllers/VisitHistoryController.php` — new.
- `resources/views/history/index.blade.php` — new. Bare placeholder — still uses the *old* `<x-app-layout>` when first created (Task 3), then gets swapped to `<x-sidebar-layout>` along with everything else in Task 4.
- `routes/web.php` — modify (twice: Task 2 adds `history.index`, wires `dashboard` to the new controller).
- `app/View/Components/SidebarLayout.php` — new. Mirrors `AppLayout`/`GuestLayout`.
- `resources/views/layouts/sidebar.blade.php` — new. The shell markup (desktop aside + mobile header/bottom-bar), carries the existing branding `@php` block over from `layouts/app.blade.php`.
- `resources/views/layouts/sidebar-nav.blade.php` — new. The nav item list + Admin group, rendered twice by `sidebar.blade.php` via a `$variant` prop (`'sidebar'` or `'bottom'`).
- `resources/views/admin/companies/index.blade.php`, `admin/departments/index.blade.php`, `admin/employees/index.blade.php`, `admin/settings/edit.blade.php`, `admin/users/index.blade.php`, `dashboard.blade.php`, `profile/edit.blade.php`, `visits/create.blade.php`, `visits/index.blade.php`, `history/index.blade.php` — modify (Task 4). Swap `<x-app-layout>` → `<x-sidebar-layout>` (open and close tag only).
- `app/View/Components/AppLayout.php`, `resources/views/layouts/app.blade.php`, `resources/views/layouts/navigation.blade.php` — deleted in Task 4 once the above swap is complete.
- Tests: `tests/Feature/IconComponentTest.php`, `tests/Feature/DashboardStubTest.php`, `tests/Feature/HistoryStubTest.php`, `tests/Feature/SidebarLayoutTest.php` — all new.

---

### Task 1: Design tokens and icon component

**Files:**
- Modify: `tailwind.config.js`
- Create: `resources/views/components/icon.blade.php`
- Test: `tests/Feature/IconComponentTest.php`

**Interfaces:**
- Produces: `<x-icon name="..." />` Blade component, consumed by Task 4's nav partial. Also produces the new Tailwind token classes (`bg-surface-container-lowest`, `text-on-surface-variant`, `text-label-md`, etc.), consumed by Task 3/4's markup.

- [ ] **Step 1: Write the failing test**

```php
<?php

test('the icon component renders a material symbols span with the given name', function () {
    $html = Blade::render('<x-icon name="home" class="text-xl" />');

    expect($html)->toContain('material-symbols-outlined');
    expect($html)->toContain('home');
    expect($html)->toContain('text-xl');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/IconComponentTest.php`
Expected: FAIL — `x-icon` component doesn't exist yet (view `components.icon` not found).

- [ ] **Step 3: Add the icon component**

```blade
<span class="material-symbols-outlined select-none {{ $attributes->get('class') }}" aria-hidden="true">{{ $name }}</span>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/IconComponentTest.php`
Expected: PASS (1 test)

- [ ] **Step 5: Add the design tokens to `tailwind.config.js`**

Modify `tailwind.config.js` — add these entries to `theme.extend`, alongside the existing `fontFamily` and `colors.primary`/`colors.'on-primary'` (which stay exactly as they are):

```js
theme: {
    extend: {
        fontFamily: {
            sans: ['Noto Sans', 'Noto Sans Arabic', ...defaultTheme.fontFamily.sans],
        },
        colors: {
            primary: 'var(--color-primary)',
            'on-primary': 'var(--color-on-primary)',
            secondary: '#565e74',
            'on-secondary': '#ffffff',
            tertiary: '#3f4f65',
            'on-tertiary': '#ffffff',
            error: '#ba1a1a',
            'on-error': '#ffffff',
            'error-container': '#ffdad6',
            'on-error-container': '#93000a',
            background: '#f7f9fb',
            'on-background': '#191c1e',
            surface: '#f7f9fb',
            'surface-container-lowest': '#ffffff',
            'surface-container-low': '#f2f4f6',
            'surface-container': '#eceef0',
            'surface-container-high': '#e6e8ea',
            'surface-variant': '#e0e3e5',
            'on-surface': '#191c1e',
            'on-surface-variant': '#434656',
            outline: '#737688',
            'outline-variant': '#c3c5d9',
            success: '#0f9d58',
            'success-container': '#d3f5df',
            warning: '#b06000',
            'warning-container': '#ffe6c7',
        },
        borderRadius: {
            DEFAULT: '0.125rem',
            lg: '0.25rem',
            xl: '0.5rem',
            full: '0.75rem',
        },
        spacing: {
            base: '8px',
            'stack-sm': '12px',
            'stack-md': '24px',
            'stack-lg': '48px',
            gutter: '24px',
        },
        fontSize: {
            'display-lg': ['48px', { lineHeight: '56px', letterSpacing: '-0.02em', fontWeight: '700' }],
            'headline-md': ['24px', { lineHeight: '32px', letterSpacing: '-0.01em', fontWeight: '600' }],
            'headline-md-mobile': ['20px', { lineHeight: '28px', fontWeight: '600' }],
            'headline-sm': ['20px', { lineHeight: '28px', fontWeight: '600' }],
            'body-lg': ['18px', { lineHeight: '28px', fontWeight: '400' }],
            'body-md': ['16px', { lineHeight: '24px', fontWeight: '400' }],
            'label-md': ['14px', { lineHeight: '20px', letterSpacing: '0.01em', fontWeight: '600' }],
            'label-sm': ['12px', { lineHeight: '16px', fontWeight: '500' }],
        },
    },
},
```

(Trimmed the React config's `-fixed`/`-fixed-dim`/`inverse-*`/`container` color variants and `container-padding-*` spacing keys — nothing in this plan's own pages or the four other planned sub-projects' known screens uses them; add on demand later rather than carrying unused tokens.)

- [ ] **Step 6: Run the full suite and build assets to check for regressions**

Run: `php artisan test && npm run build`
Expected: both succeed; `npm run build` confirms the config change is syntactically valid.

- [ ] **Step 7: Commit**

```bash
git add tailwind.config.js resources/views/components/icon.blade.php tests/Feature/IconComponentTest.php
git commit -m "feat: add design tokens and icon component for the UI overhaul"
```

---

### Task 2: Dashboard route becomes a controller

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DashboardStubTest.php`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: the `dashboard` named route continues to resolve exactly as it does today (same URL, name, middleware) — Task 4's nav partial depends on this route name already existing and working.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Setting;
use App\Models\User;

test('the dashboard route still resolves to the dashboard view via a controller', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertViewIs('dashboard');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/DashboardStubTest.php`
Expected: This actually PASSES against the existing closure route too —
the point of this test is to pin the route's name/view/middleware before
the refactor, so a future regression here is caught. Confirm it passes
now (RED would mean something about the current route is already broken,
which would itself need investigating before continuing), then proceed
to Step 3 regardless.

- [ ] **Step 3: Create the controller**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard');
    }
}
```

- [ ] **Step 4: Update the route**

Modify `routes/web.php`. Add the import:

```php
use App\Http\Controllers\DashboardController;
```

Replace:

```php
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');
```

with:

```php
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth')->name('dashboard');
```

- [ ] **Step 5: Run test to verify it still passes**

Run: `php artisan test tests/Feature/DashboardStubTest.php`
Expected: PASS (1 test) — same route name, same view, same middleware,
now via a controller.

- [ ] **Step 6: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/DashboardController.php routes/web.php tests/Feature/DashboardStubTest.php
git commit -m "refactor: convert the dashboard route from a closure to a controller"
```

---

### Task 3: History stub page

**Files:**
- Create: `app/Http/Controllers/VisitHistoryController.php`
- Create: `resources/views/history/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/HistoryStubTest.php`

**Interfaces:**
- Consumes: nothing from Task 1 or 2.
- Produces: the `history.index` named route, which must resolve before Task 4's nav partial is introduced anywhere (that partial calls `route('history.index')` on every page render).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\Setting;
use App\Models\User;

test('the history page renders for an authenticated user', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/history');

    $response->assertOk();
    $response->assertSee('Visitor History');
});

test('the history page requires authentication', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $response = $this->get('/history');

    $response->assertRedirect('/login');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/HistoryStubTest.php`
Expected: FAIL with a 404 — the `/history` route doesn't exist yet.

- [ ] **Step 3: Create the controller**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class VisitHistoryController extends Controller
{
    public function index(): View
    {
        return view('history.index');
    }
}
```

- [ ] **Step 4: Create the view**

This still uses the *old* layout tag for now — Task 4 swaps it, along
with every other page, in one pass:

```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl px-4 py-6 md:px-8 md:py-10">
        <h1 class="text-xl font-semibold">{{ __('Visitor History') }}</h1>
        <p class="mt-1 text-gray-600">{{ __('Comprehensive logs of all facility access events.') }}</p>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Add the route**

Modify `routes/web.php`. Add the import:

```php
use App\Http\Controllers\VisitHistoryController;
```

Add, inside the existing `Route::middleware('auth')->group(...)` block
(alongside `/profile`, `/visits`, etc.):

```php
    Route::get('/history', [VisitHistoryController::class, 'index'])->name('history.index');
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/HistoryStubTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/VisitHistoryController.php resources/views/history/index.blade.php routes/web.php tests/Feature/HistoryStubTest.php
git commit -m "feat: add visitor history stub page"
```

---

### Task 4: Sidebar layout, nav partial, and cutover

**Files:**
- Create: `app/View/Components/SidebarLayout.php`
- Create: `resources/views/layouts/sidebar.blade.php`
- Create: `resources/views/layouts/sidebar-nav.blade.php`
- Modify: `resources/views/admin/companies/index.blade.php`, `admin/departments/index.blade.php`, `admin/employees/index.blade.php`, `admin/settings/edit.blade.php`, `admin/users/index.blade.php`, `dashboard.blade.php`, `profile/edit.blade.php`, `visits/create.blade.php`, `visits/index.blade.php`, `history/index.blade.php` (layout tag only)
- Delete: `app/View/Components/AppLayout.php`, `resources/views/layouts/app.blade.php`, `resources/views/layouts/navigation.blade.php`
- Test: `tests/Feature/SidebarLayoutTest.php`

**Interfaces:**
- Consumes: `<x-icon name="..." />` from Task 1; the `dashboard` route from Task 2; the `history.index` route from Task 3 — both must already exist, since the nav partial built in this task calls `route('dashboard')` and `route('history.index')` on every single page it's included on, including this task's own test pages.
- Produces: `<x-sidebar-layout>` Blade tag, consumed by all 10 pages listed above (already wired in this same task).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('an admin in company mode sees all nav sections including departments and employees', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Home');
    $response->assertSee('Scan');
    $response->assertSee('Visitors');
    $response->assertSee('History');
    $response->assertSee('Admin');
    $response->assertSee('Departments');
    $response->assertSee('Employees');
    $response->assertDontSee('Companies');
});

test('an admin in building mode sees companies instead of departments and employees', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Companies');
    $response->assertDontSee('Departments');
    $response->assertDontSee('Employees');
});

test('a receptionist sees no admin section at all', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    Role::firstOrCreate(['name' => 'receptionist']);
    $user = User::factory()->create();
    $user->assignRole('receptionist');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertDontSee('Departments');
    $response->assertDontSee('Employees');
    $response->assertDontSee('Companies');
});

test('the header slot still renders on pages that pass one', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile');

    $response->assertOk();
    $response->assertSee(__('Profile'));
});

test('every existing page that used the old layout still renders under the new one', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/dashboard')->assertOk();
    $this->actingAs($admin)->get('/profile')->assertOk();
    $this->actingAs($admin)->get('/visits')->assertOk();
    $this->actingAs($admin)->get('/visits/create')->assertOk();
    $this->actingAs($admin)->get('/history')->assertOk();
    $this->actingAs($admin)->get('/admin/departments')->assertOk();
    $this->actingAs($admin)->get('/admin/employees')->assertOk();
    $this->actingAs($admin)->get('/admin/users')->assertOk();
    $this->actingAs($admin)->get('/admin/settings')->assertOk();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/SidebarLayoutTest.php`
Expected: FAIL — `x-sidebar-layout` doesn't exist; "Home"/"Scan"/"History
text isn't rendered anywhere yet (the current nav only has
"Dashboard"/"Visits"/admin links).

- [ ] **Step 3: Create the SidebarLayout component class**

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class SidebarLayout extends Component
{
    public function render(): View
    {
        return view('layouts.sidebar');
    }
}
```

- [ ] **Step 4: Create the nav partial**

```blade
@php
    $navItems = [
        ['route' => 'dashboard', 'label' => __('Home'), 'icon' => 'home'],
        ['route' => 'visits.create', 'label' => __('Scan'), 'icon' => 'qr_code_scanner'],
        ['route' => 'visits.index', 'label' => __('Visitors'), 'icon' => 'groups'],
        ['route' => 'history.index', 'label' => __('History'), 'icon' => 'history'],
    ];
    $mode = \App\Models\Setting::current()?->deployment_mode;
    $isSidebar = ($variant ?? 'sidebar') === 'sidebar';
@endphp

<nav class="{{ $isSidebar ? 'flex flex-1 flex-col gap-1 p-3' : 'fixed inset-x-0 bottom-0 flex border-t border-outline-variant bg-surface-container-lowest' }}">
    @foreach ($navItems as $item)
        <a
            href="{{ route($item['route']) }}"
            class="{{ $isSidebar
                ? 'flex items-center gap-3 rounded-lg px-4 py-3 text-label-md ' . (request()->routeIs($item['route']) ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container')
                : 'flex flex-1 flex-col items-center gap-0.5 py-2 text-label-sm ' . (request()->routeIs($item['route']) ? 'text-primary' : 'text-on-surface-variant') }}"
        >
            <x-icon :name="$item['icon']" />
            {{ $item['label'] }}
        </a>
    @endforeach

    @role('admin')
        @if ($isSidebar)
            <div class="mt-4 border-t border-outline-variant pt-4">
                <p class="px-4 text-label-sm text-on-surface-variant">{{ __('Admin') }}</p>

                @if ($mode === 'company')
                    <a href="{{ route('admin.departments.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-3 text-label-md {{ request()->routeIs('admin.departments.*') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' }}">
                        <x-icon name="apartment" /> {{ __('Departments') }}
                    </a>
                    <a href="{{ route('admin.employees.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-3 text-label-md {{ request()->routeIs('admin.employees.*') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' }}">
                        <x-icon name="badge" /> {{ __('Employees') }}
                    </a>
                @else
                    <a href="{{ route('admin.companies.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-3 text-label-md {{ request()->routeIs('admin.companies.*') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' }}">
                        <x-icon name="apartment" /> {{ __('Companies') }}
                    </a>
                @endif

                <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-3 text-label-md {{ request()->routeIs('admin.users.*') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' }}">
                    <x-icon name="group" /> {{ __('Users') }}
                </a>
                <a href="{{ route('admin.settings.edit') }}" class="flex items-center gap-3 rounded-lg px-4 py-3 text-label-md {{ request()->routeIs('admin.settings.*') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' }}">
                    <x-icon name="settings" /> {{ __('Settings') }}
                </a>
            </div>
        @else
            <a
                href="{{ $mode === 'company' ? route('admin.departments.index') : route('admin.companies.index') }}"
                class="flex flex-1 flex-col items-center gap-0.5 py-2 text-label-sm {{ request()->routeIs('admin.*') ? 'text-primary' : 'text-on-surface-variant' }}"
            >
                <x-icon name="shield_person" /> {{ __('Admin') }}
            </a>
        @endif
    @endrole
</nav>
```

- [ ] **Step 5: Create the sidebar layout view**

Copy the `@php` branding block, `<head>` contents, and `@isset($header)`
handling verbatim (in spirit — restructured around the new shell markup)
from `resources/views/layouts/app.blade.php`:

```blade
@php
    $brandSettings = \App\Models\Setting::current();
    $brandPrimary = $brandSettings->primary_color ?? '#0F172A';
    $brandOnPrimary = $brandSettings?->onPrimaryColor() ?? '#FFFFFF';
    $brandFaviconUrl = $brandSettings?->favicon_path ? \Illuminate\Support\Facades\Storage::url($brandSettings->favicon_path) : asset('favicon.ico');
    $brandLogoUrl = $brandSettings?->logo_path ? \Illuminate\Support\Facades\Storage::url($brandSettings->logo_path) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="{{ $brandFaviconUrl }}">
        <style>
            :root {
                --color-primary: {{ $brandPrimary }};
                --color-on-primary: {{ $brandOnPrimary }};
            }
        </style>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-background md:flex">
            <aside class="hidden w-64 shrink-0 flex-col border-r border-outline-variant bg-surface-container-lowest md:flex">
                <div class="flex items-center justify-center gap-2 border-b border-outline-variant px-6 py-6">
                    @if ($brandLogoUrl)
                        <img src="{{ $brandLogoUrl }}" alt="{{ config('app.name') }}" class="h-20 object-contain">
                    @else
                        <x-application-logo class="h-20 w-auto fill-current text-on-surface" />
                    @endif
                </div>

                @include('layouts.sidebar-nav', ['variant' => 'sidebar'])

                <div class="border-t border-outline-variant p-4">
                    <p class="text-label-md text-on-surface">{{ Auth::user()->name }}</p>
                    <p class="text-label-sm text-on-surface-variant">{{ Auth::user()->getRoleNames()->first() === 'admin' ? __('Administrator') : __('Receptionist') }}</p>
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="flex items-center gap-2 text-label-md text-error">
                            <x-icon name="logout" /> {{ __('Sign out') }}
                        </button>
                    </form>
                </div>
            </aside>

            <div class="flex min-h-screen flex-1 flex-col">
                <header class="flex items-center justify-between border-b border-outline-variant bg-surface-container-lowest px-4 py-3 md:hidden">
                    <span class="text-headline-md-mobile text-primary">{{ config('app.name') }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" aria-label="{{ __('Sign out') }}" class="text-on-surface-variant">
                            <x-icon name="logout" />
                        </button>
                    </form>
                </header>

                @isset($header)
                    <div class="border-b border-outline-variant bg-surface-container-lowest px-4 py-4 md:px-8">
                        {{ $header }}
                    </div>
                @endisset

                <main class="flex-1 pb-20 md:pb-0">
                    {{ $slot }}
                </main>

                @include('layouts.sidebar-nav', ['variant' => 'bottom'])
            </div>
        </div>
    </body>
</html>
```

- [ ] **Step 6: Swap the layout tag in every consuming view**

In each of these 10 files, change the opening `<x-app-layout>` to
`<x-sidebar-layout>` and the closing `</x-app-layout>` to
`</x-sidebar-layout>` — no other line changes:

- `resources/views/admin/companies/index.blade.php`
- `resources/views/admin/departments/index.blade.php`
- `resources/views/admin/employees/index.blade.php`
- `resources/views/admin/settings/edit.blade.php`
- `resources/views/admin/users/index.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/profile/edit.blade.php`
- `resources/views/visits/create.blade.php`
- `resources/views/visits/index.blade.php`
- `resources/views/history/index.blade.php` (created in Task 3 with the old tag)

- [ ] **Step 7: Delete the old layout files**

```bash
rm app/View/Components/AppLayout.php resources/views/layouts/app.blade.php resources/views/layouts/navigation.blade.php
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test tests/Feature/SidebarLayoutTest.php`
Expected: PASS (5 tests)

- [ ] **Step 9: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS — every existing test that renders one of the 10 swapped
pages now goes through the new layout; a leftover `<x-app-layout>`
reference anywhere would fail every one of those tests with a missing
view/class error.

- [ ] **Step 10: Commit**

```bash
git add app/View/Components/SidebarLayout.php resources/views/layouts/sidebar.blade.php resources/views/layouts/sidebar-nav.blade.php resources/views/admin resources/views/dashboard.blade.php resources/views/profile/edit.blade.php resources/views/visits resources/views/history tests/Feature/SidebarLayoutTest.php
git add -u app/View/Components/AppLayout.php resources/views/layouts/app.blade.php resources/views/layouts/navigation.blade.php
git commit -m "feat: replace top-nav layout with sidebar shell across all pages"
```

(The second `git add -u` stages the three deletions — `git add -u` on an
already-deleted path stages the removal; a plain `git add` on a path that
no longer exists errors instead.)

---

## Final Steps

- [ ] Run `php artisan test` once more for the whole suite.
- [ ] Run `npm run build` to confirm the new Tailwind classes compile (no
      unrecognized utility errors) and the bundle size looks sane.
- [ ] Manually verify in the browser: desktop width shows the sidebar,
      narrow/mobile width (resize below Tailwind's `md` breakpoint, 768px)
      shows the bottom tab bar instead; switch locale to Arabic and
      confirm the sidebar still renders correctly RTL-mirrored; confirm a
      custom primary color (Admin > Settings) still applies to the active
      nav item's highlight.
- [ ] Push to `main` per this project's established workflow (direct
      commits, no feature branches).
