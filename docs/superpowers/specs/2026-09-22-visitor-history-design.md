# UI Overhaul Sub-Project 4: Visitor History — Design Spec

Date: 2026-09-22

## Purpose

Replace the bare `/history` stub page (built in UI-overhaul sub-project 1
purely so the sidebar nav had a real destination) with a genuine
filterable log of ALL visits — not just the currently-open ones already
shown on `/visits`. This is the single biggest functional gap between the
Laravel port and the reference React app: today there is no way to see
past/closed visits, filter them, or export them.

Reference: `../visitor-management/frontend/src/pages/VisitorHistoryPage.tsx`
(UI) and `../visitor-management/backend/src/modules/visits/visits.routes.ts`
(filter contract, `GET /` and `GET /export`, already read this session).
The reference app is company-mode-only for this feature; this spec adds
the building-mode branch it doesn't have.

## Non-goals

- No live/AJAX filtering — a plain GET form that reloads the page, matching
  the rest of this app (its only JS pattern anywhere is the small CPR
  autocomplete on the check-in form).
- No change to the reference's JSON `page`/`pageSize` API contract — this
  app is server-rendered Blade throughout, so Laravel's built-in
  query-string paginator is used instead; there is no JSON API surface to
  match.
- No retrofit of CPR masking onto the existing `/visits` (currently-open)
  page — History is a new page and starts with masking from day one; the
  older page is out of scope here.
- No changes to the check-in/check-out flow, the dashboard, or any admin
  page.

## Flow

1. A user (any authenticated role — the reference gates `/export` to
   RECEPTIONIST/ADMIN, but every role in this app can already reach
   `/visits`, so History carries the same `auth` gate as everything else,
   no new role restriction) opens **History** from the sidebar.
2. They see a filter bar (search, date range, department-or-company,
   status) and a paginated table of every visit matching the current
   filters, most recent first.
3. Submitting the filter form reloads the page with the new query string;
   the form redisplays the active filters.
4. Clicking **Export to CSV** downloads exactly the filtered set (no
   pagination limit) as a CSV file, with the CPR number unmasked (an
   authorized export, per the reference's own comment on this exact
   behavior).
5. No visits match the filters → a plain "No records match these
   filters" message, not an empty table or an error.

## Components

### Filter query building (`app/Models/Visit.php`, modify — add a query scope)

To avoid duplicating filter logic between the paginated view and the CSV
export, both build their query through one local scope:

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

`date_to` gets `23:59:59` appended so a same-day range (`date_from` ==
`date_to`) includes visits checked in any time that day, not just at
midnight — a literal port of the reference's own `dateTo` handling
(`new Date(\`${dateTo}T23:59:59\`)` in `VisitorHistoryPage.tsx`).

### `VisitHistoryController` (modify — replace the stub `index()`, add `export()`)

```php
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

`fputcsv` is PHP's own CSV writer — handles quoting/escaping correctly
without hand-rolling the reference's `escapeCsv` helper, and needs no new
dependency.

### View (`resources/views/history/index.blade.php`, rewrite)

Filter form (GET, no AJAX) with: text input `search`, date inputs
`date_from`/`date_to`, a select that's `name="department_id"` in company
mode or `name="company_id"` in building mode, a `status` select
(`''`/`inside`/`checked_out`), a submit button, and an "Export to CSV"
link built from `route('history.export', $filters)` so it carries the
same query string.

Table columns, in order: Visitor Name, CPR (masked — `Str::mask($cpr, '•', 0, -4)`,
Laravel's built-in string-masking helper, no custom regex needed),
Mobile, Company, Department (blank/dash in building mode), Person to
Visit (employee name or company name per mode), Check-In, Check-Out,
Status (reusing the same open/closed language already used on
`/visits`). Empty state: "No records match these filters." Pagination
links via `{{ $visits->links() }}`.

### Routes (`routes/web.php`, modify)

```php
Route::get('/history', [VisitHistoryController::class, 'index'])->name('history.index');
Route::get('/history/export', [VisitHistoryController::class, 'export'])->name('history.export');
```

Both inside the existing `auth`-only group (no new role gate — see Flow).

## Security

- CPR is masked on-screen (matches the reference's own visitor-facing
  masking) but unmasked in the CSV export, which the reference itself
  treats as an authorized, deliberate exception — carried over as-is
  rather than re-litigated.
- The `filtered()` scope only ever compares against caller-supplied scalar
  values through Eloquent's query builder (parameter binding throughout),
  so there's no SQL injection surface from the free-text `search` filter.

## Error handling

| Case | Behavior |
|---|---|
| No filters at all | Full unfiltered history, paginated, most recent first |
| No visits match the filters | "No records match these filters," no table |
| `date_from` after `date_to` | No special handling — the query simply returns nothing, same as any other no-match case; not treated as a validation error |
| Export with the same filters as an empty page | CSV with header row only, no data rows |
| Building mode, no `company_id` filter selected | All companies' visits shown, exactly like company mode's default (no department filter selected) |

## Testing plan

- One test per filter in isolation: search (by each of name/CPR/mobile/company),
  date range (inclusive boundaries, exclusive outside range), status
  (inside vs. checked out), department filter (company mode), company
  filter (building mode).
- Pagination: seed more than one page's worth of visits, confirm page 2
  via `?page=2` shows the correct slice and total count.
- CSV export: correct header row, correct row count for a filtered set,
  CPR appears unmasked in the CSV vs. masked in the HTML page for the
  same visit.
- Empty state: a filter combination matching nothing shows the "No
  records match these filters" message, not an error or a blank table.
- Mode branching: building mode's filter form shows a Company select, not
  a Department select, and the "Person to Visit" column shows the company
  name.

## Documentation

No README changes — this is an in-app feature with no installation or
configuration impact.
