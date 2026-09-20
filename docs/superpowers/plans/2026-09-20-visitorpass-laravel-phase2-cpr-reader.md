# VisitorPass Laravel — Phase 2 (CPR Reader Integration) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the GCC CardRead Server CPR reader integration (browser-side scan → auto-fill) plus two supporting read-only lookup endpoints (returning-visitor mobile auto-fill, CPR autocomplete) into the registration form built in Phase 1.

**Architecture:** The hardware call is pure client-side Alpine.js on the registration page — Laravel never talks to the reader. Two new small JSON GET endpoints under a dedicated `VisitorLookupController` back the autocomplete/auto-fill UX; both are read-only and require no schema changes (Phase 1's `visitors` table already has everything needed).

**Tech Stack:** Alpine.js (already loaded via Breeze), a small vanilla `fetch` to `http://localhost:5050`, Laravel JSON responses.

**Spec:** `docs/superpowers/specs/2026-09-20-visitorpass-laravel-phase2-cpr-reader-design.md`

## Global Constraints

- The hardware `fetch` must use `Content-Type: text/plain`, not `application/json` — the GCC server has no CORS preflight handler, so a JSON content-type triggers a blocked preflight; `text/plain` is a CORS "simple request" and the server still JSON-parses the raw body regardless of the declared type.
- Company-name extraction from a scanned card tries `EmployerName`, then `SponserNameEnglish`, then `EmploymentNameEnglish`, in that order — carried over from the React app, still unverified against a real work-permit card.
- The reader-unavailable path must never block registration — the form must stay fully usable as manual entry if the hardware fetch fails.
- Both new endpoints require `auth` (receptionist or admin) — no public access.
- `/visitors/autocomplete` returns at most 8 results.

## Review Focus

- **Typing a CPR that matches no visitor** — autocomplete must return an empty list (not a 500 or an error state that blocks continued typing).
- **Looking up a CPR that doesn't exist** — `/visitors/lookup` must return 404 cleanly, and the Alpine handler must treat that as "no auto-fill," not as a failure that blocks the form.
- **A malformed/missing `q` or `cpr` query parameter** — must not 500; treat as no results / not found.
- **The GCC server being unreachable** (service stopped, wrong network) — the scan button must show a clear fallback message and leave every field manually editable, never lock the form.
- **A guest (unauthenticated) hitting either endpoint directly** — must be redirected/blocked like every other `auth`-scoped route, not leak visitor data.

---

## File Structure

```
app/
  Http/
    Controllers/
      VisitorLookupController.php
resources/
  views/
    visits/
      create.blade.php (modified — Alpine component added)
routes/
  web.php (modified — two new GET routes)
tests/Feature/
  VisitorLookupTest.php
```

---

## Task 1: Visitor lookup endpoint (exact CPR match)

**Files:**
- Create: `app/Http/Controllers/VisitorLookupController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/VisitorLookupTest.php`

**Interfaces:**
- Consumes: `Visitor` model (Phase 1, Task 8) — `cpr_number`, `name`, `company_name`, `mobile_number` columns.
- Produces: `GET /visitors/lookup?cpr=...` — JSON `{name, company_name, mobile_number}` on match (200), empty JSON object `{}` on no match (200) — chosen over 404 so the Alpine fetch handler doesn't need separate success/error branches for "no match" vs. "network error" (Review Focus: "no auto-fill, not a failure").

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/VisitorLookupTest.php`:

```php
<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;

beforeEach(function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $this->receptionist = User::factory()->create();
    $this->receptionist->assignRole('receptionist');
});

test('looking up an existing cpr returns the saved visitor details', function () {
    Visitor::create([
        'cpr_number' => '990101123',
        'name' => 'Ali Visitor',
        'company_name' => 'Acme',
        'mobile_number' => '33445566',
    ]);

    $response = $this->actingAs($this->receptionist)->getJson('/visitors/lookup?cpr=990101123');

    $response->assertOk();
    $response->assertJson([
        'name' => 'Ali Visitor',
        'company_name' => 'Acme',
        'mobile_number' => '33445566',
    ]);
});

test('looking up a cpr with no match returns an empty object', function () {
    $response = $this->actingAs($this->receptionist)->getJson('/visitors/lookup?cpr=000000000');

    $response->assertOk();
    $response->assertExactJson([]);
});

test('lookup with a missing cpr parameter returns an empty object instead of an error', function () {
    $response = $this->actingAs($this->receptionist)->getJson('/visitors/lookup');

    $response->assertOk();
    $response->assertExactJson([]);
});

test('a guest cannot access the lookup endpoint', function () {
    $response = $this->getJson('/visitors/lookup?cpr=990101123');

    $response->assertUnauthorized();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/VisitorLookupTest.php`
Expected: FAIL (no route, no controller)

- [ ] **Step 3: Create the controller**

```bash
php artisan make:controller VisitorLookupController
```

```php
<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorLookupController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $cpr = $request->query('cpr');

        if (! $cpr) {
            return response()->json([]);
        }

        $visitor = Visitor::where('cpr_number', $cpr)->first();

        if (! $visitor) {
            return response()->json([]);
        }

        return response()->json([
            'name' => $visitor->name,
            'company_name' => $visitor->company_name,
            'mobile_number' => $visitor->mobile_number,
        ]);
    }
}
```

- [ ] **Step 4: Wire the route**

In `routes/web.php`, inside the existing `auth` middleware group (alongside the `/visits` routes from Phase 1):

```php
use App\Http\Controllers\VisitorLookupController;

Route::get('/visitors/lookup', [VisitorLookupController::class, 'lookup'])->name('visitors.lookup');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/VisitorLookupTest.php`
Expected: PASS (4/4)

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add returning-visitor lookup endpoint by CPR number"
```

---

## Task 2: Visitor autocomplete endpoint (prefix match)

**Files:**
- Modify: `app/Http/Controllers/VisitorLookupController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/VisitorLookupTest.php`

**Interfaces:**
- Consumes: `Visitor` model (Phase 1, Task 8).
- Produces: `GET /visitors/autocomplete?q=...` — JSON array of up to 8 `{id, cpr_number, name}` objects, prefix-matched on `cpr_number`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/VisitorLookupTest.php`:

```php
test('autocomplete returns visitors matching a cpr prefix', function () {
    Visitor::create(['cpr_number' => '990101123', 'name' => 'Ali Visitor']);
    Visitor::create(['cpr_number' => '990101124', 'name' => 'Fatima Visitor']);
    Visitor::create(['cpr_number' => '880202111', 'name' => 'Not A Match']);

    $response = $this->actingAs($this->receptionist)->getJson('/visitors/autocomplete?q=990101');

    $response->assertOk();
    $response->assertJsonCount(2);
    $response->assertJsonFragment(['name' => 'Ali Visitor']);
    $response->assertJsonFragment(['name' => 'Fatima Visitor']);
});

test('autocomplete caps results at 8', function () {
    for ($i = 0; $i < 10; $i++) {
        Visitor::create([
            'cpr_number' => '9901011' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'name' => "Visitor {$i}",
        ]);
    }

    $response = $this->actingAs($this->receptionist)->getJson('/visitors/autocomplete?q=990101');

    $response->assertOk();
    $response->assertJsonCount(8);
});

test('autocomplete with no match returns an empty array', function () {
    $response = $this->actingAs($this->receptionist)->getJson('/visitors/autocomplete?q=000000');

    $response->assertOk();
    $response->assertExactJson([]);
});

test('autocomplete with a missing q parameter returns an empty array', function () {
    $response = $this->actingAs($this->receptionist)->getJson('/visitors/autocomplete');

    $response->assertOk();
    $response->assertExactJson([]);
});

test('a guest cannot access the autocomplete endpoint', function () {
    $response = $this->getJson('/visitors/autocomplete?q=990101');

    $response->assertUnauthorized();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/VisitorLookupTest.php`
Expected: FAIL (new tests error — no route/method yet; the 4 Task 1 tests still pass)

- [ ] **Step 3: Add the method**

Modify `app/Http/Controllers/VisitorLookupController.php`, adding this method:

```php
public function autocomplete(Request $request): JsonResponse
{
    $query = $request->query('q');

    if (! $query) {
        return response()->json([]);
    }

    $visitors = Visitor::where('cpr_number', 'like', $query . '%')
        ->orderBy('cpr_number')
        ->limit(8)
        ->get(['id', 'cpr_number', 'name']);

    return response()->json($visitors);
}
```

- [ ] **Step 4: Wire the route**

In `routes/web.php`, alongside the lookup route:

```php
Route::get('/visitors/autocomplete', [VisitorLookupController::class, 'autocomplete'])->name('visitors.autocomplete');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/VisitorLookupTest.php`
Expected: PASS (9/9)

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add CPR autocomplete endpoint"
```

---

## Task 3: CPR scan + auto-fill + autocomplete on the registration form

**Files:**
- Modify: `resources/views/visits/create.blade.php`

**Interfaces:**
- Consumes: `GET /visitors/lookup?cpr=...` and `GET /visitors/autocomplete?q=...` (Tasks 1–2), the existing `cpr_number`/`name`/`company_name`/`mobile_number` form fields (Phase 1, Task 9).
- Produces: nothing consumed by later tasks — this is the last task of Phase 2.

This task has no Pest test — the scan button talks to a real external
device (`http://localhost:5050`) that doesn't exist in the test
environment, and autocomplete/lookup wiring is DOM/JS behavior outside
Pest's reach. Verification is manual, against the actual reader hardware
and browser, per the spec's stated testing boundary.

- [ ] **Step 1: Add the Alpine component and scan button**

Modify `resources/views/visits/create.blade.php`. Wrap the existing form in an Alpine `x-data` block and add a "Scan CPR" button plus an autocomplete dropdown under the CPR field. Replace the form's opening tag and the CPR Number field block with:

```blade
<div x-data="cprScan()">
    <form method="POST" action="{{ route('visits.store') }}" @submit="stopAutocomplete()">
        @csrf

        <div class="bg-blue-50 rounded p-4 mb-4">
            <h2 class="font-semibold mb-2">{{ __('Visitor Information') }}</h2>

            <div class="mb-3 relative">
                <label for="cpr_number">{{ __('CPR Number') }}</label>
                <input
                    id="cpr_number"
                    name="cpr_number"
                    type="text"
                    class="border rounded ps-3 pe-3 py-2 block w-full"
                    value="{{ old('cpr_number') }}"
                    aria-describedby="cpr_number-error"
                    x-model="cprNumber"
                    @input="onCprInput()"
                    @blur="lookupCpr()"
                    autocomplete="off"
                    required
                >
                @error('cpr_number')
                    <p id="cpr_number-error" class="text-red-600 text-sm">{{ $message }}</p>
                @enderror

                <ul
                    x-show="suggestions.length > 0"
                    class="absolute z-10 bg-white border rounded w-full mt-1 shadow"
                >
                    <template x-for="suggestion in suggestions" :key="suggestion.id">
                        <li
                            class="px-3 py-2 hover:bg-gray-100 cursor-pointer"
                            @click="selectSuggestion(suggestion)"
                            x-text="suggestion.cpr_number + ' — ' + suggestion.name"
                        ></li>
                    </template>
                </ul>
            </div>

            <div class="mb-3">
                <label for="name">{{ __('Visitor Name') }}</label>
                <input id="name" name="name" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" x-model="visitorName" required>
            </div>

            <div>
                <label for="company_name">{{ __('Company Name') }}</label>
                <input id="company_name" name="company_name" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" x-model="companyName">
            </div>
        </div>
```

- [ ] **Step 2: Add the mobile number field binding and scan button**

Modify the "Visit Details" section's mobile number input to bind to Alpine state, and add the Scan CPR button plus a status message above it:

```blade
        <div class="bg-amber-50 rounded p-4 mb-4">
            <h2 class="font-semibold mb-2">{{ __('Visit Details') }}</h2>

            <button type="button" @click="scanCard()" class="bg-primary text-on-primary rounded px-4 py-2 mb-3">
                {{ __('Scan CPR') }}
            </button>
            <p x-show="scanMessage" x-text="scanMessage" class="text-sm text-gray-600 mb-3"></p>

            <div class="mb-3">
                <label for="mobile_number">{{ __('Mobile Number') }}</label>
                <input id="mobile_number" name="mobile_number" type="text" class="border rounded ps-3 pe-3 py-2 block w-full" x-model="mobileNumber">
            </div>
```

(The rest of the "Visit Details" section — the `employee_id`/`company_id` select — is unchanged from Phase 1.)

- [ ] **Step 3: Close the wrapping div and add the Alpine script**

At the end of the file, after the existing `</form>` closing tag (before `</x-app-layout>`), add:

```blade
    </div>

    <script>
        function cprScan() {
            return {
                cprNumber: '',
                visitorName: '',
                companyName: '',
                mobileNumber: '',
                suggestions: [],
                scanMessage: '',
                autocompleteTimer: null,

                async scanCard() {
                    this.scanMessage = '{{ __('Scanning...') }}';

                    try {
                        const response = await fetch('http://localhost:5050/api/operation/ReadCard', {
                            method: 'POST',
                            headers: { 'Content-Type': 'text/plain' },
                            body: JSON.stringify({
                                ReadEmploymentInfo: true,
                                ReadImmigrationDetails: true,
                            }),
                        });

                        const data = await response.json();

                        if (!data || !data.CPRNumber) {
                            this.scanMessage = '{{ __('No card detected. Enter CPR manually.') }}';
                            return;
                        }

                        this.cprNumber = data.CPRNumber;
                        this.visitorName = data.NameEnglish || data.Name || '';
                        this.companyName = data.EmployerName || data.SponserNameEnglish || data.EmploymentNameEnglish || '';
                        this.scanMessage = '';

                        await this.lookupCpr();
                    } catch (error) {
                        this.scanMessage = '{{ __('Reader not available — enter CPR manually.') }}';
                    }
                },

                async lookupCpr() {
                    if (!this.cprNumber) {
                        return;
                    }

                    try {
                        const response = await fetch(`/visitors/lookup?cpr=${encodeURIComponent(this.cprNumber)}`);
                        const data = await response.json();

                        if (data && data.mobile_number) {
                            this.mobileNumber = data.mobile_number;
                        }
                    } catch (error) {
                        // Silent — never block the form on a lookup failure.
                    }
                },

                onCprInput() {
                    clearTimeout(this.autocompleteTimer);

                    if (this.cprNumber.length < 3) {
                        this.suggestions = [];
                        return;
                    }

                    this.autocompleteTimer = setTimeout(async () => {
                        try {
                            const response = await fetch(`/visitors/autocomplete?q=${encodeURIComponent(this.cprNumber)}`);
                            this.suggestions = await response.json();
                        } catch (error) {
                            this.suggestions = [];
                        }
                    }, 300);
                },

                selectSuggestion(suggestion) {
                    this.cprNumber = suggestion.cpr_number;
                    this.visitorName = suggestion.name;
                    this.suggestions = [];
                    this.lookupCpr();
                },

                stopAutocomplete() {
                    clearTimeout(this.autocompleteTimer);
                    this.suggestions = [];
                },
            };
        }
    </script>
```

- [ ] **Step 4: Run the full Phase 1 + Phase 2 backend test suite to confirm nothing broke**

Run: `php artisan test`
Expected: all tests pass (Phase 1's 65 plus Phase 2's 9 new lookup/autocomplete tests = 74)

- [ ] **Step 5: Manual verification (required — not automatable)**

With the actual eReveler GCC CardRead Server running and a real CPR card:
1. Open the registration form, click **Scan CPR**, confirm name/CPR auto-fill.
2. For a returning visitor's CPR, confirm the mobile number auto-fills after scan.
3. Type a partial CPR by hand for a known returning visitor; confirm the autocomplete dropdown appears and clicking a suggestion fills the fields.
4. Stop the GCC CardRead Server (or block port 5050) and click **Scan CPR** again; confirm the "Reader not available" message appears and the form remains fully editable and submittable manually.
5. For an expat/work-permit card if available, confirm whichever of `EmployerName`/`SponserNameEnglish`/`EmploymentNameEnglish` the real response actually uses — flag to the user if none of the three match the live response shape, since this was never verified against real hardware even in the original app.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add CPR scan, auto-fill, and autocomplete to visitor registration"
```

---

## Self-Review Notes

**Spec coverage:** Architecture (client-side hardware call, CORS text/plain workaround) → Task 3. Two lookup endpoints → Tasks 1–2. Data flow (scan → auto-fill → autocomplete → unchanged submission) → Task 3. Error handling (unreachable reader, silent lookup failure, empty-card response) → Task 3, Steps 1–3 and the manual verification script. Testing boundary (endpoints tested, hardware/JS not) → explicit in Task 3's header. All "Out of Scope" items (keyboard-wedge, OCR, reader-selection UI) are correctly absent from every task.

**Placeholder scan:** no TBD/TODO; every step has runnable code.

**Type consistency:** `Visitor` model fields (`cpr_number`, `name`, `company_name`, `mobile_number`) used identically in Tasks 1, 2, and 3's JS — no renamed fields between tasks. The `lookup` response shape (`{name, company_name, mobile_number}` or `{}`) defined in Task 1 is consumed by Task 3's `lookupCpr()` exactly as shaped.

**Review Focus coverage confirmed:**
- No-match autocomplete → tested in Task 2 ("autocomplete with no match returns an empty array").
- No-match lookup → tested in Task 1 ("looking up a cpr with no match returns an empty object") and consumed gracefully in Task 3.
- Missing query parameter → tested in both Task 1 and Task 2.
- Reader unreachable → handled and manually verified in Task 3 (automated coverage isn't possible for a real external device, per the spec).
- Guest access blocked → tested in both Task 1 and Task 2.
