# UI/UX Redesign — Design Plan

Presentation-layer redesign of VisitorPass. No change to routes, controllers'
behaviour, validation, auth, database, scanner logic or language switching.

## Product framing

VisitorPass is sold as an **installable product** (one install per building
or company), not a multi-tenant SaaS. So:
- No tenant switcher, plans, billing, trial banners or "workspace" chrome.
- Each install carries the **customer's brand**: their logo, tagline and
  primary colour from Admin → Settings drive the UI. DeVerra Technologies
  appears only as the maker ("v1.0 · Developed by DeVerra Technologies").
- The configurable primary colour stays the single action/accent colour.
  The rest of the palette (navy, slate neutrals, status colours) is fixed so
  any brand colour sits on a calm, consistent base. Recommended brand colour
  for DeVerra demos: `#2563EB`.

## Analysis of the existing UI (what exists and must keep working)

| Area | Implementation | Preserve |
|---|---|---|
| Shell | `x-sidebar-layout` (class component) → `layouts/sidebar`; nav in `layouts/sidebar-nav` (sidebar + mobile bottom variant) | Locale PATCH form, logout POST, profile link, `$header`/`$title` slots, `border-e`, bottom nav classes |
| Dashboard | `DashboardController` counts + 5 recent visits | All four metrics, recent activity, link to visits |
| Register / Scan | `visits/create`, Alpine `cprScan()` (card reader fetch, CPR lookup, autocomplete) | Every `x-model`, id, name, handler; "Scan CPR" button text |
| Visitors | `visits/index`, open visits + PATCH check-out | Check-out form and `btn btn-secondary btn-sm` |
| History | GET filter form, paginated table, CSV export, CPR masking | Filter names, export link, masking |
| Admin | `x-admin-page` tabs (aria-current), lists, edit pages, bulk import partial, settings (branding + SMTP + test email) | Tab markup, all forms, confirm-on-delete |
| Auth/profile | Breeze components (`x-text-input`, buttons, modal) | Behaviour of every form |
| Icons | Material Symbols Outlined (font) | Keep as the single icon family |
| i18n | `lang/ar.json`, `dir="rtl"`, guard test for missing Arabic | Every new string gets Arabic |

## Tokens

Colour (Tailwind names kept so every page re-skins at once):

| Role | Token | Value |
|---|---|---|
| Action / accent | `primary` | customer setting (CSS var) |
| Page background | `background` | `#F8FAFC` |
| Surface | `surface-container-lowest` | `#FFFFFF` |
| Subtle fill | `surface-container-low` / `surface-container` | `#F1F5F9` / `#E2E8F0`-tint |
| Border | `outline-variant` | `#E2E8F0` |
| Text | `on-surface` / navy | `#0F172A` |
| Secondary text | `on-surface-variant` | `#64748B` |
| Navy 2 | `navy-2` | `#1E293B` |
| Success / Warning / Danger / Info | | `#16A34A` / `#F59E0B` / `#DC2626` / `#0EA5E9` |

Type: **Inter** for Latin (tabular numerals on all figures), **Noto Sans
Arabic** for Arabic. Page title 28/700, section 18/600, card label 14/500,
metric 32/700 tabular, body 14–15, secondary 12–13.

Shape: cards `rounded-xl` (12px) + 1px border + barely-there shadow; buttons
and inputs `rounded-lg` (8px), 44px tall; badges pill.

Icons: Material Symbols Outlined at weight 350, 20px — reads as a line icon set.

Motion: colour/background transitions 150ms, drawer 200ms, dropdown 150ms;
`prefers-reduced-motion` disables them. No entrance animations.

## Layout

```
Desktop ≥1024                         Mobile <1024
┌──────────┬──────────────────────┐   ┌──────────────────────────┐
│ Logo     │ Wed, 8 Oct  [ع] (AB) │   │ ☰  Logo         [ع] (AB) │
│ OVERVIEW │──────────────────────│   │──────────────────────────│
│  Home    │ Page title  [Action] │   │ Page title               │
│ VISITORS │ subtitle             │   │ [Action]                 │
│  Scan    │                      │   │ content (cards stack,    │
│  Visitors│ content (max 1440)   │   │ tables scroll inside)    │
│  History │                      │   │──────────────────────────│
│ SYSTEM   │                      │   │ Home Scan Visitors Hist. │
│  Admin   │                      │   └──────────────────────────┘
│──────────│                      │   ☰ opens the full sidebar as
│ (AB) Name│                      │   a drawer (incl. language,
│ ع  ⎋     │                      │   sign out — today missing on
│ v1.0 ... │                      │   mobile).
└──────────┴──────────────────────┘
```

Section labels in the sidebar are sentence case ("Overview", "Visitor
management", "System") — not all-caps — per the type guidance.

## The one bold move

The dashboard leads with **who is in the building right now**: the
"Currently inside" count is the emphasised KPI and a live "Inside now" panel
lists those visitors beside recent activity. Everything else stays quiet.
(Adds one read-only query to `DashboardController` — the same open-visits
data the Visitors page already shows; no new behaviour.)

## Page plans

- **Dashboard:** greeting with the signed-in user's first name + today's
  date; primary "Register visitor" action in the header; 4 KPI cards with
  derived hints; two columns: Recent activity table (Visitor, Host, Check-in,
  Check-out, Status) | Inside now list; designed empty states.
- **Register / Scan:** two-panel workflow — a focused card-reader panel
  (insert card → Scan CPR → status) beside the visitor/visit form. Same
  Alpine component and fields.
- **Visitors:** "Currently inside" table with duration since check-in and the
  existing check-out button; empty state.
- **History:** filter bar card, table with status badges and tidy dates,
  existing pagination and export.
- **Admin:** tab bar restyled; lists become tables with Edit/Delete; edit
  pages and settings become sectioned cards (Branding, Email notifications).
- **Profile, login, password pages, install, setup, errors:** same tokens
  and components.

## Not doing

- No notifications UI (none exists), no charts, no new search on Visitors
  (none exists), no QR scanning (the scanner is a CPR card reader).
- No change to the stored default brand colour (DB default) — set it in
  Settings.
