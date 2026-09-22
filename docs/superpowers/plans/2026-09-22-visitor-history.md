# UI Overhaul Sub-Project 4: Visitor History Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the bare `/history` stub with a filterable, paginated log of every visit (not just open ones), plus a CSV export of the filtered set.

**Architecture:** A single `Visit::scopeFiltered()` query scope builds the shared filter logic (search, date range, status, department/company) once; `VisitHistoryController::index()` uses it with pagination for the HTML page, `VisitHistoryController::export()` uses it without pagination for a streamed CSV. No JS — a plain GET form reloads the page, matching every other page in this app except the check-in form's CPR autocomplete.

**Tech Stack:** Laravel 13, Blade, Eloquent query scopes, `response()->streamDownload()` + PHP's built-in `fputcsv`.

**Spec:** [docs/superpowers/specs/2026-09-22-visitor-history-design.md](../specs/2026-09-22-visitor-history-design.md)

## Global Constraints

- No AJAX/live filtering — a plain GET form, page reload on submit.
- No change to `/visits` (the currently-open-visits page) — CPR masking and any other new behavior here does not retrofit onto that page.
- `date_to` gets `23:59:59` appended before comparison, so a same-day range includes the whole day (matches the reference app's own handling).
- CSV export shows the CPR **unmasked** (an authorized export); the HTML table shows it **masked** (`Str::mask($cpr, '•', 0, -4)` — everything except the last 4 characters).
- Building mode's filter form shows a Company select in the exact slot company mode shows a Department select; there is no case where both or neither appear.
- No new role gate — History is reachable by any authenticated user, exactly like `/visits` today.

## Review Focus

- **`date_from` after `date_to`** — must return an empty result set silently, not throw or 500. Covered in Task 2.
- **A filter combination matching nothing** — must show "No records match these filters," not an empty table with headers only or a crash. Covered in Task 2.
- **Building mode with no company filter selected** — must show all companies' visits, exactly like company mode's default when no department is selected. Covered in Task 2.
- **CSV export of an empty filtered set** — must still produce a valid CSV with the header row and zero data rows, not an empty file or an error. Covered in Task 3.
- **Search matching the visitor's free-text `company_name` field** — this is distinct from the related `Company` model (used only for building-mode host selection); a search for a company name must match visits where that text appears in `visitors.company_name`, regardless of deployment mode. Covered in Task 1.

---

## File Structure

- `app/Models/Visit.php` — modify. Add `scopeFiltered()`.
- `app/Http/Controllers/VisitHistoryController.php` — modify. Replace the stub `index()`; add `export()`.
- `resources/views/history/index.blade.php` — rewrite. Filter form, table, pagination links.
- `routes/web.php` — modify. Add `GET /history/export`.
- Tests: `tests/Feature/VisitFilteredScopeTest.php`, `tests/Feature/VisitHistoryPageTest.php`, `tests/Feature/VisitHistoryExportTest.php` — all new.

---

### Task 1: `Visit::scopeFiltered()` query scope

**Files:**
- Modify: `app/Models/Visit.php`
- Test: `tests/Feature/VisitFilteredScopeTest.php`

**Interfaces:**
- Produces: `Visit::filtered(array $filters): Builder`, where `$filters` may contain any of `search`, `date_from`, `date_to`, `department_id`, `company_id`, `status` (`'inside'` or `'checked_out'`), all optional. Consumed by Task 2 (`index()`) and Task 3 (`export()`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Visit;
use App\Models\Visitor;

function makeVisit(array $visitorAttrs, array $visitAttrs): Visit
{
    $visitor = Visitor::create(array_merge(['cpr_number' => uniqid('cpr_'), 'name' => 'Someone'], $visitorAttrs));

    return $visitor->visits()->create($visitAttrs);
}

test('filtered search matches the visitor name', function () {
    $match = makeVisit(['name' => 'Jane Findable'], ['check_in_at' => now()]);
    $noMatch = makeVisit(['name' => 'Other Person'], ['check_in_at' => now()]);

    $results = Visit::filtered(['search' => 'Findable'])->get();

    expect($results->pluck('id'))->toContain($match->id)->not->toContain($noMatch->id);
});

test('filtered search matches the CPR number', function () {
    $match = makeVisit(['cpr_number' => '900011122'], ['check_in_at' => now()]);
    $noMatch = makeVisit(['cpr_number' => '900099999'], ['check_in_at' => now()]);

    $results = Visit::filtered(['search' => '1112'])->get();

    expect($results->pluck('id'))->toContain($match->id)->not->toContain($noMatch->id);
});

test('filtered search matches the mobile number', function () {
    $match = makeVisit(['name' => 'A'], ['mobile_number' => '33445566', 'check_in_at' => now()]);
    $noMatch = makeVisit(['name' => 'B'], ['mobile_number' => '11223344', 'check_in_at' => now()]);

    $results = Visit::filtered(['search' => '3344556'])->get();

    expect($results->pluck('id'))->toContain($match->id)->not->toContain($noMatch->id);
});

test('filtered search matches the visitor company_name free-text field, not the related Company model', function () {
    $match = makeVisit(['company_name' => 'Acme Traders'], ['check_in_at' => now()]);
    $noMatch = makeVisit(['company_name' => 'Other Co'], ['check_in_at' => now()]);

    $results = Visit::filtered(['search' => 'Acme'])->get();

    expect($results->pluck('id'))->toContain($match->id)->not->toContain($noMatch->id);
});

test('filtered date range is inclusive of the from date', function () {
    $inRange = makeVisit(['name' => 'A'], ['check_in_at' => '2026-01-10 09:00:00']);
    $before = makeVisit(['name' => 'B'], ['check_in_at' => '2026-01-09 23:59:59']);

    $results = Visit::filtered(['date_from' => '2026-01-10'])->get();

    expect($results->pluck('id'))->toContain($inRange->id)->not->toContain($before->id);
});

test('filtered date range includes the entire to date, not just midnight', function () {
    $lateSameDay = makeVisit(['name' => 'A'], ['check_in_at' => '2026-01-10 23:30:00']);
    $nextDay = makeVisit(['name' => 'B'], ['check_in_at' => '2026-01-11 00:00:01']);

    $results = Visit::filtered(['date_to' => '2026-01-10'])->get();

    expect($results->pluck('id'))->toContain($lateSameDay->id)->not->toContain($nextDay->id);
});

test('filtered status inside returns only open visits', function () {
    $open = makeVisit(['name' => 'A'], ['check_in_at' => now()]);
    $closed = makeVisit(['name' => 'B'], ['check_in_at' => now(), 'check_out_at' => now()]);

    $results = Visit::filtered(['status' => 'inside'])->get();

    expect($results->pluck('id'))->toContain($open->id)->not->toContain($closed->id);
});

test('filtered status checked_out returns only closed visits', function () {
    $open = makeVisit(['name' => 'A'], ['check_in_at' => now()]);
    $closed = makeVisit(['name' => 'B'], ['check_in_at' => now(), 'check_out_at' => now()]);

    $results = Visit::filtered(['status' => 'checked_out'])->get();

    expect($results->pluck('id'))->toContain($closed->id)->not->toContain($open->id);
});

test('filtered department_id matches only that department', function () {
    $it = Department::create(['name' => 'IT']);
    $hr = Department::create(['name' => 'HR']);
    $itEmployee = Employee::create(['department_id' => $it->id, 'name' => 'IT Person']);
    $hrEmployee = Employee::create(['department_id' => $hr->id, 'name' => 'HR Person']);

    $itVisit = makeVisit(['name' => 'A'], ['employee_id' => $itEmployee->id, 'department_id' => $it->id, 'check_in_at' => now()]);
    $hrVisit = makeVisit(['name' => 'B'], ['employee_id' => $hrEmployee->id, 'department_id' => $hr->id, 'check_in_at' => now()]);

    $results = Visit::filtered(['department_id' => $it->id])->get();

    expect($results->pluck('id'))->toContain($itVisit->id)->not->toContain($hrVisit->id);
});

test('filtered company_id matches only that company', function () {
    $acme = Company::create(['name' => 'Acme']);
    $other = Company::create(['name' => 'Other']);

    $acmeVisit = makeVisit(['name' => 'A'], ['company_id' => $acme->id, 'check_in_at' => now()]);
    $otherVisit = makeVisit(['name' => 'B'], ['company_id' => $other->id, 'check_in_at' => now()]);

    $results = Visit::filtered(['company_id' => $acme->id])->get();

    expect($results->pluck('id'))->toContain($acmeVisit->id)->not->toContain($otherVisit->id);
});

test('filtered with no filters returns everything', function () {
    $a = makeVisit(['name' => 'A'], ['check_in_at' => now()]);
    $b = makeVisit(['name' => 'B'], ['check_in_at' => now()->subDay()]);

    $results = Visit::filtered([])->get();

    expect($results->pluck('id'))->toContain($a->id)->toContain($b->id);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/VisitFilteredScopeTest.php`
Expected: FAIL — `Visit::filtered()` (the `scopeFiltered` local scope) doesn't exist yet.

- [ ] **Step 3: Add the scope**

Modify `app/Models/Visit.php`. Add the import and method:

```php
use Illuminate\Database\Eloquent\Builder;
```

```php
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->whereHas('visitor', function ($vq) use ($search) {
                    $vq->where('name', 'like', "%{$search}%")
                        ->orWhere('cpr_number', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->where('check_in_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->where('check_in_at', '<=', $date.' 23:59:59'))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when(($filters['status'] ?? null) === 'inside', fn ($q) => $q->whereNull('check_out_at'))
            ->when(($filters['status'] ?? null) === 'checked_out', fn ($q) => $q->whereNotNull('check_out_at'));
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/VisitFilteredScopeTest.php`
Expected: PASS (11 tests)

- [ ] **Step 5: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Models/Visit.php tests/Feature/VisitFilteredScopeTest.php
git commit -m "feat: add Visit::filtered() query scope for history search/filters"
```

---

### Task 2: History page — filters, table, pagination

**Files:**
- Modify: `app/Http/Controllers/VisitHistoryController.php`
- Rewrite: `resources/views/history/index.blade.php`
- Test: `tests/Feature/VisitHistoryPageTest.php`

**Interfaces:**
- Consumes: `Visit::filtered(array $filters): Builder` (Task 1).
- Produces: nothing consumed by later tasks in this plan (Task 3's export route is independent).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;

test('history page shows all visits, not just open ones', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);

    $open = Visitor::create(['cpr_number' => '900000001', 'name' => 'Open Visitor']);
    $open->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $closed = Visitor::create(['cpr_number' => '900000002', 'name' => 'Closed Visitor']);
    $closed->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now(), 'check_out_at' => now()]);

    $response = $this->actingAs($user)->get('/history');

    $response->assertOk();
    $response->assertSee('Open Visitor');
    $response->assertSee('Closed Visitor');
});

test('history page masks the CPR number', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $visitor = Visitor::create(['cpr_number' => '900001234', 'name' => 'Jane']);
    $visitor->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history');

    $response->assertOk();
    $response->assertDontSee('900001234');
    $response->assertSee('1234');
});

test('a search filter narrows the results shown', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $findable = Visitor::create(['cpr_number' => '900000003', 'name' => 'Findable Person']);
    $findable->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);
    $other = Visitor::create(['cpr_number' => '900000004', 'name' => 'Other Person']);
    $other->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history?search=Findable');

    $response->assertOk();
    $response->assertSee('Findable Person');
    $response->assertDontSee('Other Person');
});

test('a filter combination matching nothing shows a friendly empty state', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/history?search=NoSuchVisitorAtAll');

    $response->assertOk();
    $response->assertSee('No records match these filters');
});

test('date_from after date_to returns no results without erroring', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $visitor = Visitor::create(['cpr_number' => '900000005', 'name' => 'Someone']);
    $visitor->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history?date_from=2030-01-01&date_to=2020-01-01');

    $response->assertOk();
    $response->assertSee('No records match these filters');
});

test('company mode shows a department filter, building mode shows a company filter', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    Department::create(['name' => 'IT']);

    $response = $this->actingAs($user)->get('/history');

    $response->assertOk();
    $response->assertSee('name="department_id"', false);
    $response->assertDontSee('name="company_id"', false);
});

test('building mode shows all companies visits when no company filter is selected', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    $user = User::factory()->create();
    $acme = Company::create(['name' => 'Acme']);
    $other = Company::create(['name' => 'Other']);
    $acmeVisitor = Visitor::create(['cpr_number' => '900000006', 'name' => 'Acme Visitor']);
    $acmeVisitor->visits()->create(['company_id' => $acme->id, 'check_in_at' => now()]);
    $otherVisitor = Visitor::create(['cpr_number' => '900000007', 'name' => 'Other Visitor']);
    $otherVisitor->visits()->create(['company_id' => $other->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history');

    $response->assertOk();
    $response->assertSee('name="company_id"', false);
    $response->assertDontSee('name="department_id"', false);
    $response->assertSee('Acme Visitor');
    $response->assertSee('Other Visitor');
});

test('pagination shows 20 visits per page and links to page 2', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);

    foreach (range(1, 25) as $i) {
        $visitor = Visitor::create(['cpr_number' => "90000010{$i}", 'name' => "Visitor {$i}"]);
        $visitor->visits()->create([
            'employee_id' => $employee->id,
            'department_id' => $department->id,
            'check_in_at' => now()->subMinutes(25 - $i),
        ]);
    }

    $page1 = $this->actingAs($user)->get('/history');
    $page2 = $this->actingAs($user)->get('/history?page=2');

    $page1->assertOk();
    $page1->assertSee('Visitor 25'); // most recent, first page
    $page1->assertDontSee('Visitor 1'); // oldest, should be on page 2
    $page2->assertOk();
    $page2->assertSee('Visitor 1');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/VisitHistoryPageTest.php`
Expected: FAIL — the current stub view has no table, no filters, no pagination.

- [ ] **Step 3: Update the controller**

Replace the contents of `app/Http/Controllers/VisitHistoryController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Setting;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $mode = Setting::current()->deployment_mode;
        $filters = $request->only(['search', 'date_from', 'date_to', 'department_id', 'company_id', 'status']);

        $visits = Visit::with('visitor', 'employee', 'company', 'department')
            ->filtered($filters)
            ->orderByDesc('check_in_at')
            ->paginate(20)
            ->withQueryString();

        return view('history.index', [
            'visits' => $visits,
            'filters' => $filters,
            'mode' => $mode,
            'departments' => $mode === 'company' ? Department::orderBy('name')->get() : collect(),
            'companies' => $mode === 'building' ? Company::orderBy('name')->get() : collect(),
        ]);
    }
}
```

- [ ] **Step 4: Rewrite the view**

Replace the contents of `resources/views/history/index.blade.php`:

```blade
<x-sidebar-layout>
    <div class="mx-auto max-w-6xl px-4 py-6 md:px-8 md:py-10">
        <h1 class="text-headline-md text-on-surface">{{ __('Visitor History') }}</h1>
        <p class="mt-1 text-body-md text-on-surface-variant">{{ __('Comprehensive logs of all facility access events.') }}</p>

        <form method="GET" action="{{ route('history.index') }}" class="mt-6 grid grid-cols-1 gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label class="text-label-sm text-on-surface-variant">{{ __('Search') }}</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Name, CPR, or mobile...') }}" class="border rounded ps-3 pe-3 py-2 block w-full">
            </div>
            <div>
                <label class="text-label-sm text-on-surface-variant">{{ __('From') }}</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="border rounded ps-3 pe-3 py-2 block w-full">
            </div>
            <div>
                <label class="text-label-sm text-on-surface-variant">{{ __('To') }}</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="border rounded ps-3 pe-3 py-2 block w-full">
            </div>
            @if ($mode === 'company')
                <div>
                    <label class="text-label-sm text-on-surface-variant">{{ __('Department') }}</label>
                    <select name="department_id" class="border rounded ps-3 pe-3 py-2 block w-full">
                        <option value="">{{ __('All Departments') }}</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(($filters['department_id'] ?? null) == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div>
                    <label class="text-label-sm text-on-surface-variant">{{ __('Company') }}</label>
                    <select name="company_id" class="border rounded ps-3 pe-3 py-2 block w-full">
                        <option value="">{{ __('All Companies') }}</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="text-label-sm text-on-surface-variant">{{ __('Status') }}</label>
                <select name="status" class="border rounded ps-3 pe-3 py-2 block w-full">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="inside" @selected(($filters['status'] ?? null) === 'inside')>{{ __('Inside') }}</option>
                    <option value="checked_out" @selected(($filters['status'] ?? null) === 'checked_out')>{{ __('Checked Out') }}</option>
                </select>
            </div>
            <div class="flex items-end gap-2 lg:col-span-5">
                <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Filter') }}</button>
                <a href="{{ route('history.export', $filters) }}" class="border rounded px-4 py-2">{{ __('Export to CSV') }}</a>
            </div>
        </form>

        <div class="mt-6 overflow-x-auto rounded-xl border border-outline-variant bg-surface-container-lowest">
            @if ($visits->isEmpty())
                <p class="p-6 text-center text-body-md text-on-surface-variant">{{ __('No records match these filters.') }}</p>
            @else
                <table class="w-full min-w-[900px] text-start">
                    <thead class="bg-surface-container-low text-label-sm uppercase tracking-wide text-on-surface-variant">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Visitor Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('CPR Number') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Mobile') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Company') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Department') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Person to Visit') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Check-In') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Check-Out') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant text-body-md">
                        @foreach ($visits as $visit)
                            <tr>
                                <td class="px-4 py-3 font-medium text-on-surface">{{ $visit->visitor->name }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ \Illuminate\Support\Str::mask($visit->visitor->cpr_number, '•', 0, -4) }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->visitor->mobile_number }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->visitor->company_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->department?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->employee?->name ?? $visit->company?->name }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->check_in_at->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->check_out_at?->format('g:i A') ?? '—' }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $visit->isOpen() ? __('Inside') : __('Checked Out') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="mt-4">
            {{ $visits->links() }}
        </div>
    </div>
</x-sidebar-layout>
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/VisitHistoryPageTest.php`
Expected: PASS (9 tests)

- [ ] **Step 6: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/VisitHistoryController.php resources/views/history/index.blade.php tests/Feature/VisitHistoryPageTest.php
git commit -m "feat: build out the visitor history page with filters and pagination"
```

---

### Task 3: CSV export

**Files:**
- Modify: `app/Http/Controllers/VisitHistoryController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/VisitHistoryExportTest.php`

**Interfaces:**
- Consumes: `Visit::filtered(array $filters): Builder` (Task 1).
- Produces: route `history.export` (`GET /history/export`), consumed by Task 2's already-shipped "Export to CSV" link (no changes needed there — it already builds this URL).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;

test('export produces a CSV with the correct header row', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/history/export');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();
    expect($csv)->toContain('Visitor Name,CPR Number,Mobile Number,Company,Department,Person to Visit,Check-In,Check-Out,Status');
});

test('export shows the CPR number unmasked, unlike the HTML page', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $visitor = Visitor::create(['cpr_number' => '900009999', 'name' => 'Jane']);
    $visitor->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history/export');

    $response->assertOk();
    expect($response->streamedContent())->toContain('900009999');
});

test('export respects the same filters as the HTML page', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();
    $department = Department::create(['name' => 'IT']);
    $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sam Host']);
    $findable = Visitor::create(['cpr_number' => '900000008', 'name' => 'Findable Person']);
    $findable->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);
    $other = Visitor::create(['cpr_number' => '900000009', 'name' => 'Other Person']);
    $other->visits()->create(['employee_id' => $employee->id, 'department_id' => $department->id, 'check_in_at' => now()]);

    $response = $this->actingAs($user)->get('/history/export?search=Findable');

    $csv = $response->streamedContent();
    expect($csv)->toContain('Findable Person');
    expect($csv)->not->toContain('Other Person');
});

test('export of an empty filtered set still produces a valid CSV with just the header', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/history/export?search=NoSuchVisitorAtAll');

    $response->assertOk();
    $csv = trim($response->streamedContent());
    expect(substr_count($csv, "\n"))->toBe(0);
    expect($csv)->toContain('Visitor Name,CPR Number');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/VisitHistoryExportTest.php`
Expected: FAIL with a 404 — the `/history/export` route doesn't exist yet.

- [ ] **Step 3: Add the export action**

Modify `app/Http/Controllers/VisitHistoryController.php`. Add the import:

```php
use Symfony\Component\HttpFoundation\StreamedResponse;
```

Add the method:

```php
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['search', 'date_from', 'date_to', 'department_id', 'company_id', 'status']);

        $visits = Visit::with('visitor', 'employee', 'company', 'department')
            ->filtered($filters)
            ->orderByDesc('check_in_at')
            ->get();

        return response()->streamDownload(function () use ($visits) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Visitor Name', 'CPR Number', 'Mobile Number', 'Company', 'Department', 'Person to Visit', 'Check-In', 'Check-Out', 'Status']);

            foreach ($visits as $visit) {
                fputcsv($handle, [
                    $visit->visitor->name,
                    $visit->visitor->cpr_number,
                    $visit->visitor->mobile_number,
                    $visit->visitor->company_name ?? '',
                    $visit->department?->name ?? '',
                    $visit->employee?->name ?? $visit->company?->name ?? '',
                    $visit->check_in_at->toIso8601String(),
                    $visit->check_out_at?->toIso8601String() ?? '',
                    $visit->isOpen() ? 'INSIDE' : 'CHECKED_OUT',
                ]);
            }

            fclose($handle);
        }, 'visitor-history-'.now()->timestamp.'.csv', ['Content-Type' => 'text/csv']);
    }
```

- [ ] **Step 4: Add the route**

Modify `routes/web.php`. Find the existing `/history` route:

```php
    Route::get('/history', [VisitHistoryController::class, 'index'])->name('history.index');
```

Add directly after it:

```php
    Route::get('/history/export', [VisitHistoryController::class, 'export'])->name('history.export');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/VisitHistoryExportTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/VisitHistoryController.php routes/web.php tests/Feature/VisitHistoryExportTest.php
git commit -m "feat: add CSV export of the filtered visitor history"
```

---

## Final Steps

- [ ] Run `php artisan test` once more for the whole suite.
- [ ] Run `npm run build` to confirm nothing broke (this feature adds no new Tailwind tokens, but confirm anyway).
- [ ] Manually verify in the browser against the local MySQL instance: apply each filter individually and in combination, confirm pagination links work, click "Export to CSV" and open the downloaded file to confirm the CPR is unmasked there vs. masked on the page, and confirm building mode shows the Company filter instead of Department.
- [ ] Push to `main` per this project's established workflow (direct commits, no feature branches).
