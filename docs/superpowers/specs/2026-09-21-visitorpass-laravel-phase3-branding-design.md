# VisitorPass Laravel — Phase 3: Branding Settings — Design

## Context

Phases 1 (core foundation) and 2 (CPR reader integration) are complete,
reviewed, and pushed. This document covers **Phase 3 only**: an admin
screen for the branding fields Phase 1's schema already reserved
(`primary_color`, `logo_path`, `favicon_path`, `tagline` on the
`settings` table) but never exposed — today changing them requires a
direct database edit.

## Scope

**In scope:**
- Admin-only `Admin > Settings` screen: primary color (manual hex
  picker), logo upload, favicon upload, tagline text.
- Runtime application of these values across the whole app (colors,
  logo, favicon), with no rebuild required after a change.
- Auto-computed white/black text color against whatever primary color is
  chosen, via a WCAG relative-luminance check — matching the original
  app's behavior.

**Explicitly out of scope:**
- Auto-suggesting a color by extracting it from the uploaded logo
  (confirmed earlier in this project: manual hex picker only).
- Active cleanup of old logo/favicon files when replaced (left orphaned
  on disk — not worth the added complexity for a few small image files).
- A public branding API endpoint (see Architecture — not needed for this
  stack).

## Architecture

- **CSS custom properties replace the compiled Tailwind colors.**
  Currently `bg-primary`/`text-on-primary` are Tailwind utility classes
  whose hex values are baked into the CSS bundle at `npm run build` time
  (`tailwind.config.js`'s static `colors` block) — a database change
  wouldn't affect anything on screen without a rebuild. Phase 3 redefines
  those two colors once, in `tailwind.config.js`, as
  `background-color: var(--color-primary)` / `color: var(--color-on-primary)`
  equivalents, and the main layout's `<head>` emits a small inline
  `<style>` block setting `--color-primary` / `--color-on-primary` fresh
  from `Setting::current()` on every request. Every existing Blade view
  keeps using `bg-primary`/`text-on-primary` unchanged — only where the
  color's value comes from changes.
- **No public branding API needed.** The original React app was a
  single-page app that had to `fetch` branding before rendering anything,
  including the login screen — hence a public `GET /api/settings`. Every
  VisitorPass Laravel page is server-rendered, so the layout simply calls
  `Setting::current()` directly, authenticated or not. This removes an
  endpoint the React app needed.
- **The favicon "browsers won't repaint on mutation" bug from the React
  app doesn't apply here.** That was specifically an SPA problem — the
  page never reloads, so mutating a `<link>` tag's `href` in place
  sometimes doesn't trigger a re-fetch. Every Blade page is a fresh
  server-render with the current favicon path already in its
  `<link rel="icon">` tag, so a normal page load already shows whatever
  is current.
- **Uploads use Laravel's standard public disk** (`storage/app/public`,
  symlinked to `public/storage` via `php artisan storage:link`) — the
  idiomatic Laravel equivalent of the original app's
  `backend/uploads/branding/`.

## Data Model

No new migration. Phase 1's `settings` table already has every column
needed (`primary_color` default `#0F172A`, `logo_path` nullable,
`favicon_path` nullable, `tagline` nullable).

The white/black contrast decision is **not stored** — it's a small,
deterministic calculation from `primary_color`'s hex value (WCAG relative
luminance), exposed as a `Setting::onPrimaryColor(): string` helper
method computed on demand. No new column, and it can never go stale
relative to whatever `primary_color` currently is.

## Admin Screen & Data Flow

- New `Admin > Settings` screen, admin-only, added to the existing
  mode-branched navigation from Phase 1.
- Fields: a native `<input type="color">` for primary color (no JS color
  picker library needed — every modern browser has one built in), a file
  input for logo, a file input for favicon, and a text input for tagline.
- On submit: validate the hex format, validate uploaded files are images
  under a sane size limit, store via `Storage::disk('public')`, update
  the singleton `Setting` row (id=1, same pattern as Phase 1's
  `Setting::current()`). Each upload gets a freshly generated filename;
  the previous file (if any) is left on disk rather than deleted.
- The main layout's `<head>` (and the guest/setup layout, for the login
  and setup-wizard screens) read `Setting::current()` once per request
  and render: the `<style>` block with the two CSS variables, a
  `<link rel="icon">` from `favicon_path` (falling back to the app's
  bundled default if unset), and the logo `<img>` from `logo_path`
  (falling back to the current text/SVG placeholder if unset). The
  tagline renders under the logo on the login screen if set, matching
  the original app.

## Error Handling

- Invalid hex format → validation error, form redisplays with the
  previously submitted value (standard Laravel `old()` behavior).
- Invalid or oversized upload → validation error; only successfully
  validated fields are written, so a bad logo upload doesn't also
  discard a valid color change submitted in the same request.
- No branding configured yet (fresh install, before any admin visits the
  Settings screen) → falls back to the default navy/white theme from
  Phase 1's design system, unchanged from today's behavior.

## Testing

- A non-admin (receptionist) is blocked from the Settings screen (403,
  matching every other admin-only screen's established pattern).
- A valid settings update persists and is reflected by
  `Setting::current()` immediately afterward.
- An invalid hex value is rejected with a validation error and does not
  change the stored `primary_color`.
- `Setting::onPrimaryColor()` returns white for a known dark color
  (`#0F172A`) and black for a known light color (`#FFFFFF`) — a couple of
  known-answer cases pinning the luminance calculation, not an exhaustive
  colorimetric test suite.
- The main layout renders the CSS variables from whatever `Setting`
  values are current, verified via a feature test asserting the rendered
  HTML contains the expected `--color-primary` value.

## Out of Scope (this phase)

- CSV bulk import (Phase 4), email notifications (Phase 5), audit logs +
  history/export (Phase 6) — all unaffected by and independent of this
  phase's work.
