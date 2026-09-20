# VisitorPass Laravel — Phase 1: Core Foundation — Design

## Context

VisitorPass Pro is a reception visitor management system currently built as
React + Node/Express + Prisma + MySQL, live in production for the Bahrain
Olympic Committee (BOC) at `C:\Users\Athul Baby\visitor-management`
(GitHub: `er-athulbaby/visitormanagement`).

This project ports it to PHP/Laravel and turns it into a **resellable
product**, because the intended hosting target for resale is shared/cPanel
hosting, which cannot run persistent Node processes.

This document covers **Phase 1 only** — the foundation every later phase
builds on. Full feature parity with the current app is the end goal, split
into the following phases, each getting its own design → spec → plan cycle:

1. **Core foundation** (this document)
2. CPR reader integration
3. Branding settings
4. CSV bulk import
5. Email notifications
6. Audit logs + history/export

## Product Model: Reselling

- **Distribution:** one Laravel install + one MySQL database per customer,
  on their own hosting/subdomain — not a shared multi-tenant SaaS. No
  tenant_id scoping, no cross-customer isolation concerns.
- **Two buyer types, chosen once per install, locked afterward:**
  - **Company mode** — a single company's reception desk. Visitors check in
    to visit a specific employee within a department (the current BOC
    model).
  - **Building mode** — a building/property manager's reception desk.
    Visitors check in to visit a company operating in the building — no
    department, no named host person, just a flat company list.
- Mode is chosen via a **first-run setup wizard**, not `.env` config, so a
  non-technical reseller/customer can self-configure a fresh install.

## Architecture

- **Stack:** Laravel (current LTS) + Blade + Livewire/Alpine for
  interactivity, MySQL via Eloquent. Chosen over a React SPA + Laravel API
  split because a single server-rendered monolith is simpler to deploy and
  maintain on shared hosting, and matches idiomatic Laravel conventions.
- **Session/cache/queue:** `database` driver for all three — safe default
  when Redis isn't guaranteed on shared hosting. Background work (e.g.
  Phase 5 emails) runs via Laravel's queue with cPanel cron calling
  `php artisan schedule:run`, not a persistent worker process.
- **Auth:** Laravel's built-in session auth (cookies + CSRF, no separate
  JWT layer needed since views are server-rendered).
- **Roles:** Spatie Laravel-Permission package, not a hardcoded enum —
  chosen because this is a reseller product where future customers may
  need additional roles (e.g. a building-manager tier). Ships with two
  seeded roles: `admin` and `receptionist`.
- **No multi-tenancy in the schema** — confirmed one-install-per-customer,
  so no tenant-scoping logic anywhere.

## Data Model

| Table | Purpose | Mode |
|---|---|---|
| `settings` | Singleton (id=1): `deployment_mode` enum, branding fields (Phase 3), SMTP config fields (Phase 5) | Both |
| `users` | id, name, email, password, locale, timestamps; role via Spatie | Both |
| `departments` | id, name | Company only |
| `employees` | id, department_id (FK), name, email (nullable) | Company only |
| `companies` | id, name, contact_email (nullable) | Building only |
| `visitors` | id, cpr_number (unique), name, company_name (nullable, visitor's own employer — unrelated to the `companies` table), mobile_number (nullable) | Both |
| `visits` | id, visitor_id (FK), mobile_number, employee_id (nullable FK), department_id (nullable FK, denormalized), company_id (nullable FK), check_in_at, check_out_at | Both |
| `audit_logs` | id, user_id (FK), action, details (json) | Both (table created now, populated from Phase 6) |

A `visits` row must have exactly one of `employee_id` / `company_id` set,
matching the install's locked `deployment_mode` — enforced in application
validation (a `FormRequest` per action), not a DB-level CHECK constraint,
to keep the migration portable across MySQL versions on varied shared
hosts.

`users.locale` stores each user's preferred language (`en`/`ar`) for
Phase 1's bilingual UI (see below).

## Setup Wizard (first run)

- Every request redirects to `/setup` while no `settings` row exists.
- Steps: (1) choose Company or Building mode, (2) create the first admin
  account, (3) optionally seed a first department (Company mode) or first
  company (Building mode) — skippable, addable later either way.
- On completion: writes the locked `settings.deployment_mode`, creates the
  admin user, redirects to login.
- `/setup` is permanently inaccessible once a `settings` row exists — no
  in-app path to silently change mode on a live install.

## Auth & RBAC

- `/admin/*` routes require the `admin` role (Spatie gate).
- Visitor register/check-in/out and history/export are open to both
  `admin` and `receptionist` — matching the RBAC widening already made on
  the live BOC deployment (receptionists have full history access).

## UI/UX Design System

- **Colors (default theme):** navy/slate professional palette — primary
  `#0F172A`, accent `#0369A1`, background `#F8FAFC`, high-contrast,
  WCAG-appropriate. This is the *default* shown before an admin
  customizes it via Phase 3's branding settings (manual hex picker + logo
  upload, same UX as the current app — no auto-color-extraction from the
  logo).
- **Typography:** Noto Sans (Latin) + Noto Sans Arabic — same type family
  across both scripts so bilingual screens stay visually consistent
  (matching weights/spacing) rather than pairing mismatched fonts.
- **Multilingual (English + Arabic, RTL):**
  - `lang/en/*.php` and `lang/ar/*.php`, `__()` used throughout Blade —
    no hardcoded UI strings.
  - Locale stored on `users.locale`; a switcher in the header.
  - `<html dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">`
    toggles direction.
  - Tailwind uses **logical properties** (`ps-*`/`pe-*`/`ms-*`/`me-*`)
    everywhere instead of physical `pl-*`/`pr-*`/`ml-*`/`mr-*`, so RTL
    doesn't need a parallel stylesheet — this is a component-authoring
    rule from the first component built, not a later retrofit.
- **Kiosk-form UX** (visitor registration is the highest-frequency
  screen): inline validation on blur, per-field errors wired via
  `aria-describedby`, plus a focusable error summary on failed submit.
- **Accessibility baseline:** 4.5:1 contrast, visible focus rings, 44×44px
  touch targets (reception desks are often touchscreen kiosks),
  `prefers-reduced-motion` respected, SVG icons only (no emoji).

## Error Handling

- Laravel's default exception handler, `APP_DEBUG=false` in production —
  no stack traces shown to users.
- All mutating actions validated through `FormRequest` classes (centralizes
  validation + authorization per action). Note: PHP/Laravel has no
  equivalent to the current Node app's "Express 4 doesn't forward async
  throws" failure class — each PHP request is its own process, so an
  uncaught exception there can't take down other requests.
- FK-constraint-violation guards (e.g. deleting a department that still
  has employees) get a pre-check + friendly error message, mirroring the
  fix already made in the current app for the same bug class.

## Testing

- Laravel's built-in PHPUnit/Pest feature tests, one file per major flow:
  - Auth/RBAC (non-admin blocked from `/admin/*`)
  - Setup wizard (mode locks correctly; `/setup` inaccessible after
    completion)
  - Visitor register + check-in/out, exercised for **both** Company mode
    and Building mode
  - Admin CRUD guards
- No browser/UI test framework for Phase 1 — feature tests hit routes
  directly, per Laravel convention.

## Out of Scope (this phase)

- CPR hardware reader integration (Phase 2)
- Branding settings UI (Phase 3 — schema field exists, no admin screen yet)
- CSV bulk import (Phase 4)
- Email notifications (Phase 5 — schema field exists, nothing sends yet)
- Audit log writing + history/export UI (Phase 6 — table exists, unused)
