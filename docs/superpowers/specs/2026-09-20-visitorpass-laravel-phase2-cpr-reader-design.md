# VisitorPass Laravel — Phase 2: CPR Reader Integration — Design

## Context

Phase 1 (core foundation) is complete, reviewed, fixed, and running locally.
This document covers **Phase 2 only**: porting the CPR smart-card reader
integration from the original React app.

The original app used a pluggable `CprReader` interface with three
implementations: `MockCprReader` (dev), `KeyboardWedgeCprReader` (unused
stub), and `GccCardReadServerReader` — the one actually in production use
at BOC, talking to the "eReveler GCC CardRead Server" Windows service at
`http://localhost:5050`.

## Scope

**In scope for Phase 2:**
- Port the GCC CardRead Server integration only (matches current BOC usage;
  the interface stays swappable so keyboard-wedge/manual-only backends can
  be added as their own later phases without a rewrite).
- Returning-visitor auto-fill (saved mobile number) after any successful
  scan.
- Manual-CPR-entry autocomplete (3+ digits, debounced, up to 8 matches).

**Explicitly out of scope for Phase 2:**
- Keyboard-wedge barcode reader backend.
- OCR/manual-only backend.
- A reader-type selection UI (single backend for now — no choice to make).
- `MockCprReader`-style dev mode (not needed; feature tests exercise the
  registration form directly by posting field values, same as Phase 1).

## Architecture

- **The hardware call lives entirely in the browser.** An Alpine.js
  component on the registration page (`resources/views/visits/create.blade.php`)
  calls `http://localhost:5050/api/operation/ReadCard` directly — Laravel's
  backend never touches this request, exactly like the React app's
  `GccCardReadServerReader`. This is not testable by Pest (a live external
  device); it is manual-verification-only, the same boundary Phase 1
  already established for anything outside the test suite's reach.
- **CORS quirk carried over unchanged, because it's a hardware constraint,
  not a design choice:** the GCC server doesn't implement an `OPTIONS`
  preflight, so requests must be sent as `Content-Type: text/plain` to
  qualify as a CORS "simple request" (the server still JSON-parses the raw
  body regardless of the declared content type).
- **Two new Laravel JSON endpoints** support the "returning visitor"
  features, both under `auth` middleware (receptionist or admin):
  - `GET /visitors/lookup?cpr=...` — exact match, returns `{name,
    company_name, mobile_number}` for auto-fill, or 404 if no match.
  - `GET /visitors/autocomplete?q=...` — prefix match on `cpr_number`,
    returns up to 8 `{id, cpr_number, name}` results.

## Data Flow

1. Receptionist clicks **Scan CPR**.
2. Alpine component POSTs `text/plain` to the GCC server, parses the
   response for name/CPR/nationality, and — for expat/work-permit cards —
   attempts company name via the fallback chain `EmployerName` →
   `SponserNameEnglish` → `EmploymentNameEnglish` (unchanged from the React
   app; still unverified against a real work-permit card, per the original
   app's own note).
3. Alpine fills `cpr_number`, `name`, `company_name`, then calls
   `GET /visitors/lookup` to auto-fill `mobile_number` if this CPR has a
   saved record. This lookup **always** runs after a successful scan —
   fixing the React app's bug where it only ran when the reader didn't
   already provide a name (a branch that, in practice, never fired with
   the real eReveler hardware, so the mobile number silently never
   auto-filled via Scan CPR).
4. While typing a CPR by hand (3+ digits, 300ms debounce), the same
   component calls `GET /visitors/autocomplete` and renders a picklist of
   matching returning visitors below the field.
5. Form submission is unchanged — it still goes through Phase 1's
   `StoreVisitRequest` / `VisitController::store()`.

## Error Handling

- **GCC server unreachable** (service not installed/stopped, wrong network
  context): the `fetch` fails; Alpine shows "Reader not available — enter
  CPR manually" and leaves the form fully usable as plain manual entry.
  The feature must degrade gracefully — it must never block registration.
- **Lookup/autocomplete request failures**: fail silently (no auto-fill, no
  dropdown). Never block typing or submission.
- **CPR reader returning an empty/removed-card response**: treated the
  same as "no card scanned yet" — no error shown, just no auto-fill.

## Testing

- `GET /visitors/lookup`: feature tests for exact match (returns saved
  data), no match (404), and unauthenticated access (redirect/403).
- `GET /visitors/autocomplete`: feature tests for prefix match returning
  results, the 8-result cap, no match (empty array), and unauthenticated
  access.
- The Alpine/hardware-scan piece itself: **no automated test** — same
  testing boundary as Phase 1. The plan will call out manual verification
  against real hardware as a required step, with the company-name
  extraction specifically flagged as unverified (same caveat the original
  app carried).

## Out of Scope (this phase)

- Keyboard-wedge and OCR/manual-only reader backends (future phases).
- Reader-type selection UI.
- Branding settings (Phase 3), CSV bulk import (Phase 4), email
  notifications (Phase 5), audit logs + history/export (Phase 6) — all
  unaffected by and independent of this phase's work.
