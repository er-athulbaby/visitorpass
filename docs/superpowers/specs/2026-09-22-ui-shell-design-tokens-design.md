# UI Overhaul, Sub-Project 1: App Shell & Design Tokens — Design Spec

Date: 2026-09-22

## Purpose

The Laravel port currently uses Breeze's default flat top-nav layout with
plain Tailwind gray/blue styling. The original React app (source at
`../visitor-management/frontend/src`, a sibling checkout on this
machine) has a materially more polished UI: a persistent sidebar nav on
desktop / bottom tab bar on mobile, built on a documented, already-approved
design token system ("Sentinel Protocol" — colors, spacing, type scale in
`frontend/tailwind.config.js`).

Bringing the whole UI up to that level is too large for one project. This
is the first of five sub-projects: get the **navigation shell and visual
foundation** in place, since every other planned sub-project (dashboard,
visitor history, admin restructure, currently-inside search) renders
inside it. This sub-project changes no business logic and adds no new
user-facing features beyond two placeholder pages — it is a structural
and visual foundation only.

The other four sub-projects (dashboard/stats, visitor history + CSV
export, admin tab restructure + bulk CSV import, currently-inside search)
are out of scope here and will each get their own brainstorm → spec →
plan cycle later.

## Non-goals

- No changes to any existing page's business logic, backend queries, or
  form behavior — only the layout wrapper changes for existing pages.
- No dashboard stats, no visitor history filters/export, no bulk CSV
  import, no admin tab restructure — these are separate sub-projects.
- No JS build-tooling changes — icons come from a webfont `<link>` tag,
  matching the React app's own approach, not a JS icon library.
- No dark mode — the React app's config has `darkMode: "class"` but
  nothing in the app currently toggles that class; out of scope until
  it's actually wired up somewhere.

## Flow

1. An authenticated user (any role) sees a persistent sidebar (desktop)
   or bottom tab bar (mobile) on every page, replacing today's top nav.
2. Sidebar items: **Home**, **Scan**, **Visitors**, **History** (all
   roles), **Admin** (admin role only, expandable to today's real admin
   destinations).
3. Clicking **Home** or **History** for the first time under this
   sub-project shows a bare placeholder page (heading only) — later
   sub-projects fill these in without touching the nav again.
4. Clicking **Scan**, **Visitors**, or any **Admin** sub-item shows
   exactly the same page as today, unchanged, just inside the new visual
   shell.
5. RTL (Arabic), the locale toggle, and logout all keep working exactly
   as they do today.

## Components

### Design tokens (`tailwind.config.js`, modify)

Copy the `theme.extend` block from
`../visitor-management/frontend/tailwind.config.js` (colors, spacing,
fontFamily, fontSize, borderRadius) into this project's
`tailwind.config.js`, merged with the existing `colors.primary` /
`colors.'on-primary'` entries already present from Phase 3 (`var(--color-primary, ...)`,
`var(--color-on-primary, ...)`) — those two entries are kept exactly as
they are (Phase 3's runtime branding depends on them), and the rest of
the React app's token set (`secondary`, `surface-container-*`,
`on-surface-variant`, `outline-variant`, `error`, `success`, `warning`,
the `label-md`/`body-md`/`headline-md`/`display-lg` type scale, the
`borderRadius` and `spacing` scales) is added alongside, unchanged.

### Icons (`resources/views/components/icon.blade.php`, new)

A trivial Blade component wrapping Google's Material Symbols Outlined
webfont, mirroring the React app's `Icon.tsx`:

```blade
<span class="material-symbols-outlined select-none {{ $attributes->get('class') }}" aria-hidden="true">{{ $name }}</span>
```

Used as `<x-icon name="home" />`. The webfont `<link>` tag is added once,
in the new sidebar layout's `<head>`, alongside the existing Google Fonts
`<link>` for Noto Sans.

### Sidebar layout (`resources/views/layouts/sidebar.blade.php`, new)

Replaces `layouts/app.blade.php` as the layout every authenticated page
uses. Keeps the existing `@php` branding block (favicon, `--color-primary`/
`--color-on-primary` CSS vars) byte-for-byte from `layouts/app.blade.php`.
Structure:

```
<div class="min-h-screen bg-background md:flex">
    <aside class="hidden w-64 ... md:flex">  {{-- logo, nav, user/logout footer --}}
    <div class="flex flex-1 flex-col">
        <header class="... md:hidden">  {{-- mobile top bar: logo + logout --}}
        <main>{{ $slot }}</main>
        <nav class="fixed inset-x-0 bottom-0 ... md:hidden">  {{-- mobile bottom tabs --}}
    </div>
</div>
```

`layouts/app.blade.php` itself is deleted once every consuming view is
repointed at `layouts/sidebar.blade.php` (see Components below) — no
page should reference the old layout after this sub-project, to avoid
two parallel authenticated shells existing at once.

### Sidebar nav content (`resources/views/layouts/sidebar-nav.blade.php`, new)

Replaces `layouts/navigation.blade.php`. Rendered twice by
`sidebar.blade.php` (once for the desktop `<aside>`, once for the mobile
bottom bar) via a `$variant` prop (`'sidebar'` or `'bottom'`) controlling
which Tailwind classes apply, since the two need different layouts
(vertical list vs. horizontal tab row) from the same item data.

Item list (Blade array, not a PHP class — this app has no existing
pattern for nav-item value objects and one isn't needed for five items):

```php
$navItems = [
    ['route' => 'dashboard', 'label' => __('Home'), 'icon' => 'home'],
    ['route' => 'visits.create', 'label' => __('Scan'), 'icon' => 'qr_code_scanner'],
    ['route' => 'visits.index', 'label' => __('Visitors'), 'icon' => 'groups'],
    ['route' => 'history.index', 'label' => __('History'), 'icon' => 'history'],
];
```

The **Admin** entry is handled separately (not in this array) since it's
role-gated and expandable, not a single link — rendered via
`@role('admin')` exactly as today's admin items are, just visually
grouped under one "Admin" heading with the existing mode-branch logic
(`Setting::current()->deployment_mode === 'company'` → Departments +
Employees; else → Companies) carried over unchanged from
`layouts/navigation.blade.php`. On the mobile bottom bar, "Admin" becomes
a single tab linking to the first available admin destination (matches
the React app's mobile nav, which also has no room for a nested admin
menu on the bottom bar).

### Home stub (`app/Http/Controllers/DashboardController.php` + `resources/views/dashboard.blade.php`, new/modify)

`routes/web.php` already has:
```php
Route::get('/dashboard', function () { return view('dashboard'); })->middleware('auth')->name('dashboard');
```
This becomes a real controller (`DashboardController@index`) for
consistency with every other route in this app (all of which use
controllers, not closures), returning the same view name. The view
becomes a single heading, "Reception Dashboard", inside the new layout —
sub-project #2 fills in stat cards and recent activity later without
touching this route or controller signature.

### History stub (`app/Http/Controllers/VisitHistoryController.php` + `resources/views/history/index.blade.php`, new)

New route `GET /history` (`history.index`), `auth`-gated like `/visits`.
Controller returns a bare view: heading "Visitor History" only. Sub-project
#4 fills in filters, pagination, and CSV export later.

### Existing pages (modify, layout tag only)

`resources/views/visits/index.blade.php`, `visits/create.blade.php`,
`admin/departments/index.blade.php`, `admin/employees/index.blade.php`,
`admin/companies/index.blade.php`, `admin/users/index.blade.php`,
`admin/settings/edit.blade.php`, `profile/edit.blade.php`,
`install/form.blade.php` (already standalone — untouched), and the
`errors/db-unreachable.blade.php`/`resources/views/emails/*` views
(already standalone — untouched): every file currently opening with
`<x-app-layout>` changes to `<x-sidebar-layout>`.

This app's `<x-app-layout>` is not an anonymous Blade component —
Breeze generated a class-based one, `app/View/Components/AppLayout.php`
(`App\View\Components\AppLayout extends Illuminate\View\Component`,
whose `render()` returns `view('layouts.app')`). The new
`app/View/Components/SidebarLayout.php` mirrors it exactly, returning
`view('layouts.sidebar')` instead — same pattern already used for
`GuestLayout`/`AppLayout`, no new convention introduced. No other line
in any of the listed view files changes.

## Error handling

| Case | Behavior |
|---|---|
| Non-admin user | Sidebar shows 4 items, no Admin section — matches today's `@role('admin')` gating exactly |
| Building mode | Admin group shows Companies instead of Departments/Employees — matches today's mode-branch exactly |
| Arabic locale | Sidebar renders RTL-mirrored (icon+label order flips via `dir="rtl"` on `<html>`, already set app-wide) |
| A page still referencing `<x-app-layout>` after this sub-project | Build/test failure by design — `layouts/app.blade.php` is deleted, so any missed reference 500s immediately and loudly rather than silently rendering the old shell |

## Testing plan

- `SidebarLayoutTest` (feature): admin user sees all 5 top-level sections
  (Home/Scan/Visitors/History/Admin) on `/dashboard`; receptionist user
  sees only 4 (no Admin); building-mode admin sees "Companies" not
  "Departments"/"Employees" in the admin group.
- `DashboardStubTest` (feature): `GET /dashboard` returns 200 and shows
  "Reception Dashboard".
- `HistoryStubTest` (feature): `GET /history` returns 200 and shows
  "Visitor History"; unauthenticated request redirects to login.
- `ExistingPagesStillRenderTest` (feature): one test per existing page
  confirming it still returns 200 under the new layout tag (regression
  guard for the mechanical `<x-app-layout>` → `<x-sidebar-layout>` swap)
  — reuses each page's existing Setting/fixture setup already established
  in earlier phases' test files rather than duplicating it.
- Manual verification: open the app in the browser at both desktop and
  mobile widths, confirm the sidebar/bottom-bar switch at the `md`
  breakpoint, confirm RTL rendering in Arabic, confirm the branding CSS
  vars (custom primary color) still apply.

## Documentation

No README changes — this sub-project has no effect on
installation/configuration, only on-screen layout.
