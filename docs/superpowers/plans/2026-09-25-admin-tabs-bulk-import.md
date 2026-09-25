# Admin Tabs + Bulk CSV Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Present admin pages as one tabbed "Administration" section and add CSV bulk import to Departments, Employees, and Companies.

**Architecture:** A tiny `App\Support\Csv` helper (plain `fgetcsv`) parses uploads and streams templates. Each entity controller gains `template()` and `import()` actions; results flash to the session and render via a shared import partial. An anonymous `<x-admin-page>` component provides heading + mode-aware tab bar + flash messages for all five admin views; the sidebar's admin group collapses to one link.

**Tech Stack:** Laravel 13, Blade, Tailwind v3 (Sentinel tokens), Pest (SQLite in-memory).

**Spec:** `docs/superpowers/specs/2026-09-25-admin-tabs-bulk-import-design.md`

## Global Constraints

- No new Composer/npm dependencies; CSV parsing via PHP `fgetcsv`.
- Existing admin URLs, controllers, and route names stay unchanged.
- Upload rule: `required|file|mimes:csv,txt|max:2048`.
- Row number reported = physical spreadsheet line (header is line 1).
- Name matching is case-insensitive (`mb_strtolower`); departments are never auto-created by employee import.
- No JS added; tabs are plain links.
- Run tests with `php artisan test` (Pest). Commit to `main` after each task, ending messages with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. CSV saved by Excel starts with a UTF-8 BOM → first header still matches (Task 1 test).
2. Row shorter/longer than the header (trailing commas dropped by an editor) → no crash, missing cells read as blank (Task 1 test).
3. Blank lines in the middle of the file → skipped, and later error rows still report the true spreadsheet line (Task 1 + Task 3 tests).
4. Same entity twice in one file → second occurrence skipped, not duplicated (Tasks 2, 3, 4 tests).
5. Headers in different case / padded with spaces (`" department name "`) → still matched (Task 1 test).

---

### Task 1: CSV helper

**Files:**
- Create: `app/Support/Csv.php`
- Modify: `tests/Pest.php` (add `csvFile()` helper)
- Test: `tests/Feature/CsvHelperTest.php`

**Interfaces:**
- Produces:
  - `App\Support\Csv::records(UploadedFile $file): array<int, array<string,string>>` — keys are physical line numbers (first data line = 2); record keys are lower-cased trimmed headers; values trimmed.
  - `App\Support\Csv::field(array $record, string ...$names): string` — first present column, `''` if none.
  - `App\Support\Csv::template(string $filename, array $headers): StreamedResponse`
  - Test helper `csvFile(string $content): Illuminate\Http\UploadedFile`

- [ ] **Step 1: Add the test helper to `tests/Pest.php`** — replace the `something()` placeholder function:

```php
function csvFile(string $content): \Illuminate\Http\UploadedFile
{
    return \Illuminate\Http\UploadedFile::fake()->createWithContent('import.csv', $content);
}
```

- [ ] **Step 2: Write the failing test** `tests/Feature/CsvHelperTest.php`:

```php
<?php

use App\Support\Csv;

test('records are keyed by spreadsheet line with lower-cased trimmed headers', function () {
    $records = Csv::records(csvFile("\xEF\xBB\xBF Department Name ,Email\r\nHR , hr@x.test\r\n\r\nIT\r\nOps,o@x.test,extra\r\n"));

    expect($records)->toBe([
        2 => ['department name' => 'HR', 'email' => 'hr@x.test'],
        4 => ['department name' => 'IT', 'email' => ''],
        5 => ['department name' => 'Ops', 'email' => 'o@x.test'],
    ]);
});

test('a header-only file has no records', function () {
    expect(Csv::records(csvFile("Name\n")))->toBe([]);
});

test('field returns the first matching column case-insensitively or blank', function () {
    $record = ['name' => 'Sam', 'email' => ''];

    expect(Csv::field($record, 'Employee Name', 'Name'))->toBe('Sam');
    expect(Csv::field($record, 'Department'))->toBe('');
});

test('template streams a BOM and the header row as csv', function () {
    $response = Csv::template('t.csv', ['Company Name', 'Contact Email']);

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($body)->toBe("\xEF\xBB\xBF\"Company Name\",\"Contact Email\"\n");
});
```

- [ ] **Step 3: Run** `php artisan test --filter=CsvHelperTest` — expected FAIL (class `App\Support\Csv` not found).

- [ ] **Step 4: Implement** `app/Support/Csv.php`:

```php
<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    /**
     * Parse an uploaded CSV whose first line is a header row.
     *
     * @return array<int, array<string, string>> keyed by spreadsheet line number
     */
    public static function records(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = null;
        $records = [];
        $line = 0;

        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $line++;
            $values = array_map(fn ($value) => trim((string) $value), $row);

            if ($header === null) {
                // Excel prepends a UTF-8 BOM, which would otherwise glue itself to the first header.
                $values[0] = trim(preg_replace('/^\xEF\xBB\xBF/', '', $values[0]));
                $header = array_map('mb_strtolower', $values);

                continue;
            }

            if (implode('', $values) === '') {
                continue;
            }

            $values = array_pad(array_slice($values, 0, count($header)), count($header), '');
            $records[$line] = array_combine($header, $values);
        }

        fclose($handle);

        return $records;
    }

    public static function field(array $record, string ...$names): string
    {
        foreach ($names as $name) {
            $key = mb_strtolower($name);

            if (array_key_exists($key, $record)) {
                return $record[$key];
            }
        }

        return '';
    }

    public static function template(string $filename, array $headers): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, ',', '"', '');
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
```

Note: `fputcsv` quotes fields containing spaces — the expected template body above reflects that.

- [ ] **Step 5: Run** `php artisan test --filter=CsvHelperTest` — expected PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Support/Csv.php tests/Pest.php tests/Feature/CsvHelperTest.php
git commit -m "feat: add CSV parsing and template helper for bulk import"
```

---

### Task 2: Department import + shared import partial

**Files:**
- Modify: `routes/web.php` (company-mode group)
- Modify: `app/Http/Controllers/Admin/DepartmentController.php`
- Create: `resources/views/admin/partials/import.blade.php`
- Modify: `resources/views/admin/departments/index.blade.php`
- Test: `tests/Feature/DepartmentImportTest.php`

**Interfaces:**
- Consumes: `Csv::records`, `Csv::field`, `Csv::template`, `csvFile()` (Task 1).
- Produces:
  - Routes `admin.departments.template` (GET `/admin/departments/template`), `admin.departments.import` (POST `/admin/departments/import`).
  - Session flash `import` = `['created' => int, 'updated' => list<string>, 'skipped' => list<string>, 'errors' => list<array{row:int,message:string}>]`.
  - Partial `admin.partials.import` taking `$route` (route-name prefix, e.g. `'admin.departments'`) and `$columns` (string shown to the user).

- [ ] **Step 1: Write the failing test** `tests/Feature/DepartmentImportTest.php`:

```php
<?php

use App\Models\Department;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('importing departments creates new ones, skips existing and in-file duplicates, and reports bad rows', function () {
    Department::create(['name' => 'Finance']);

    $response = $this->actingAs($this->admin)->post('/admin/departments/import', [
        'file' => csvFile("Department Name\nHR\nfinance\n\nhr\n".str_repeat('x', 256)."\n,\n"),
    ]);

    $response->assertRedirect('/admin/departments');
    $response->assertSessionHas('import', [
        'created' => 1,
        'updated' => [],
        'skipped' => ['finance', 'hr'],
        'errors' => [['row' => 6, 'message' => 'Missing or invalid department name']],
    ]);
    expect(Department::pluck('name')->sort()->values()->all())->toBe(['Finance', 'HR']);
});

test('the Name column is accepted as an alias', function () {
    $this->actingAs($this->admin)->post('/admin/departments/import', ['file' => csvFile("name\nLegal\n")]);

    expect(Department::where('name', 'Legal')->exists())->toBeTrue();
});

test('a file with no data rows is rejected with an error', function () {
    $this->actingAs($this->admin)
        ->post('/admin/departments/import', ['file' => csvFile("Department Name\n")])
        ->assertRedirect('/admin/departments')
        ->assertSessionHas('error', 'The CSV file has no data rows.');
});

test('the upload must be a csv file', function () {
    $this->actingAs($this->admin)
        ->post('/admin/departments/import', [])
        ->assertSessionHasErrors('file');
});

test('a receptionist cannot import departments', function () {
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    $this->actingAs($receptionist)
        ->post('/admin/departments/import', ['file' => csvFile("Name\nHR\n")])
        ->assertForbidden();
    expect(Department::count())->toBe(0);
});

test('the template downloads as csv with the department header', function () {
    $response = $this->actingAs($this->admin)->get('/admin/departments/template');

    $response->assertOk();
    $response->assertDownload('departments-template.csv');
    expect($response->streamedContent())->toContain('"Department Name"');
});

test('the departments page shows the import form and the import summary', function () {
    $this->actingAs($this->admin)
        ->post('/admin/departments/import', ['file' => csvFile("Name\nHR\n".str_repeat('x', 256)."\n")]);

    $response = $this->actingAs($this->admin)->get('/admin/departments');

    $response->assertSee(route('admin.departments.import'), false);
    $response->assertSee(route('admin.departments.template'), false);
    $response->assertSee('enctype="multipart/form-data"', false);
    $response->assertSee('1 created');
    $response->assertSee('Row 3: Missing or invalid department name');
});
```

Note: the last test relies on the flash surviving one redirect — the second `get` is the next request, so `session('import')` is still present.

- [ ] **Step 2: Run** `php artisan test --filter=DepartmentImportTest` — expected FAIL (404 on `/admin/departments/import`).

Confirm the receptionist 403 comes from the existing `admin` middleware (it returns 403 for non-admins in `AuthAndRbacTest`). If it redirects instead, change that test's assertion to match the existing admin-middleware behavior shown in `tests/Feature/AuthAndRbacTest.php`.

- [ ] **Step 3: Add routes** in `routes/web.php`, inside the `mode:company` group, above the departments resource line:

```php
        Route::get('departments/template', [DepartmentController::class, 'template'])->name('departments.template');
        Route::post('departments/import', [DepartmentController::class, 'import'])->name('departments.import');
```

- [ ] **Step 4: Add the actions** to `DepartmentController` (add `use App\Support\Csv;` and `use Symfony\Component\HttpFoundation\StreamedResponse;`):

```php
    public function template(): StreamedResponse
    {
        return Csv::template('departments-template.csv', ['Department Name']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $records = Csv::records($request->file('file'));

        if ($records === []) {
            return redirect()->route('admin.departments.index')
                ->with('error', __('The CSV file has no data rows.'));
        }

        $known = Department::pluck('name')->mapWithKeys(fn ($name) => [mb_strtolower($name) => true])->all();
        $created = 0;
        $skipped = [];
        $errors = [];

        foreach ($records as $row => $record) {
            $name = Csv::field($record, 'Department Name', 'Name', 'Department');

            if ($name === '' || mb_strlen($name) > 255) {
                $errors[] = ['row' => $row, 'message' => __('Missing or invalid department name')];

                continue;
            }

            if (isset($known[mb_strtolower($name)])) {
                $skipped[] = $name;

                continue;
            }

            Department::create(['name' => $name]);
            $known[mb_strtolower($name)] = true;
            $created++;
        }

        return redirect()->route('admin.departments.index')
            ->with('import', ['created' => $created, 'updated' => [], 'skipped' => $skipped, 'errors' => $errors]);
    }
```

- [ ] **Step 5: Create** `resources/views/admin/partials/import.blade.php`:

```blade
<section class="mb-6 rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
    <h2 class="text-headline-sm text-on-surface">{{ __('Bulk import') }}</h2>
    <p class="mt-1 text-body-md text-on-surface-variant">{{ __('Upload a CSV file with the columns: :columns', ['columns' => $columns]) }}</p>

    <form method="POST" action="{{ route($route.'.import') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
        @csrf
        <input type="file" name="file" accept=".csv,text/csv" required class="text-body-md">
        <button type="submit" class="bg-primary text-on-primary rounded-lg px-4 py-2">{{ __('Import') }}</button>
        <a href="{{ route($route.'.template') }}" class="rounded-lg border border-outline-variant px-4 py-2">{{ __('Download template') }}</a>
    </form>

    @error('file')
        <p class="mt-2 text-red-600" role="alert">{{ $message }}</p>
    @enderror

    @if ($import = session('import'))
        <div class="mt-4 space-y-2 text-body-md" role="status">
            <p class="text-on-surface">
                {{ __(':count created', ['count' => $import['created']]) }} ·
                {{ __(':count updated', ['count' => count($import['updated'])]) }} ·
                {{ __(':count skipped', ['count' => count($import['skipped'])]) }} ·
                {{ __(':count errors', ['count' => count($import['errors'])]) }}
            </p>
            @if ($import['updated'])
                <p class="text-on-surface-variant">{{ __('Updated') }}: {{ implode(', ', $import['updated']) }}</p>
            @endif
            @if ($import['skipped'])
                <p class="text-on-surface-variant">{{ __('Skipped (already exist)') }}: {{ implode(', ', $import['skipped']) }}</p>
            @endif
            @if ($import['errors'])
                <ul class="list-disc ps-5 text-red-600">
                    @foreach ($import['errors'] as $error)
                        <li>{{ __('Row :row: :message', ['row' => $error['row'], 'message' => $error['message']]) }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</section>
```

- [ ] **Step 6: Include it** in `resources/views/admin/departments/index.blade.php` directly after the `session('error')` block:

```blade
        @include('admin.partials.import', ['route' => 'admin.departments', 'columns' => 'Department Name'])
```

- [ ] **Step 7: Run** `php artisan test --filter=DepartmentImportTest` — expected PASS. Then `php artisan test` — all green.

- [ ] **Step 8: Commit**

```bash
git add routes/web.php app/Http/Controllers/Admin/DepartmentController.php resources/views/admin/partials/import.blade.php resources/views/admin/departments/index.blade.php tests/Feature/DepartmentImportTest.php
git commit -m "feat: add bulk CSV import for departments"
```

---

### Task 3: Employee import

**Files:**
- Modify: `routes/web.php` (company-mode group)
- Modify: `app/Http/Controllers/Admin/EmployeeController.php`
- Modify: `resources/views/admin/employees/index.blade.php`
- Test: `tests/Feature/EmployeeImportTest.php`

**Interfaces:**
- Consumes: `Csv` (Task 1); partial `admin.partials.import` and the `import` flash shape (Task 2).
- Produces: routes `admin.employees.template`, `admin.employees.import`.

- [ ] **Step 1: Write the failing test** `tests/Feature/EmployeeImportTest.php`:

```php
<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->it = Department::create(['name' => 'IT']);
});

test('importing employees creates, updates emails, skips, and reports each error kind', function () {
    Employee::create(['department_id' => $this->it->id, 'name' => 'Sam', 'email' => null]);
    Employee::create(['department_id' => $this->it->id, 'name' => 'Pat', 'email' => 'pat@x.test']);

    $csv = "Employee Name,Department,Email\n"
        ."Alex,it,alex@x.test\n"   // 2 created (department matched case-insensitively)
        ."sam,IT,sam@x.test\n"     // 3 updated (email filled in)
        ."Pat,IT,pat@x.test\n"     // 4 skipped (same email)
        ."Pat,IT,\n"               // 5 skipped (no email)
        ."\n"                      // 6 blank, ignored
        .",IT,\n"                  // 7 error: name
        ."Jo,Sales,\n"             // 8 error: department
        ."Jo,,\n"                  // 9 error: blank department
        ."Jo,IT,not-an-email\n"    // 10 error: email
        ."alex,IT,\n";             // 11 skipped (duplicate of row 2)

    $response = $this->actingAs($this->admin)->post('/admin/employees/import', ['file' => csvFile($csv)]);

    $response->assertRedirect('/admin/employees');
    $response->assertSessionHas('import', [
        'created' => 1,
        'updated' => ['sam (IT)'],
        'skipped' => ['Pat (IT)', 'Pat (IT)', 'alex (IT)'],
        'errors' => [
            ['row' => 7, 'message' => 'Missing or invalid employee name'],
            ['row' => 8, 'message' => 'Department "Sales" was not found'],
            ['row' => 9, 'message' => 'Department "(blank)" was not found'],
            ['row' => 10, 'message' => 'Invalid email "not-an-email"'],
        ],
    ]);
    expect(Employee::where('name', 'Alex')->first()->email)->toBe('alex@x.test');
    expect(Employee::where('name', 'Sam')->first()->email)->toBe('sam@x.test');
    expect(Employee::count())->toBe(3);
    expect(Department::count())->toBe(1);
});

test('an employee created without an email stores null', function () {
    $this->actingAs($this->admin)->post('/admin/employees/import', ['file' => csvFile("Name,Department Name\nLee,IT\n")]);

    expect(Employee::where('name', 'Lee')->first()->email)->toBeNull();
});

test('a file with no data rows is rejected with an error', function () {
    $this->actingAs($this->admin)
        ->post('/admin/employees/import', ['file' => csvFile("Employee Name,Department,Email\n")])
        ->assertSessionHas('error', 'The CSV file has no data rows.');
});

test('the template downloads with employee headers', function () {
    $response = $this->actingAs($this->admin)->get('/admin/employees/template');

    $response->assertDownload('employees-template.csv');
    expect($response->streamedContent())->toContain('"Employee Name",Department,Email');
});

test('the employees page shows the import form', function () {
    $this->actingAs($this->admin)->get('/admin/employees')
        ->assertSee(route('admin.employees.import'), false)
        ->assertSee(route('admin.employees.template'), false);
});
```

- [ ] **Step 2: Run** `php artisan test --filter=EmployeeImportTest` — expected FAIL (404).

- [ ] **Step 3: Add routes** inside the `mode:company` group, above the employees resource line:

```php
        Route::get('employees/template', [EmployeeController::class, 'template'])->name('employees.template');
        Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
```

- [ ] **Step 4: Add the actions** to `EmployeeController` (add `use App\Support\Csv;` and `use Symfony\Component\HttpFoundation\StreamedResponse;`):

```php
    public function template(): StreamedResponse
    {
        return Csv::template('employees-template.csv', ['Employee Name', 'Department', 'Email']);
    }

    /**
     * Departments must already exist: unknown names are row errors rather than
     * auto-created, so typos don't fragment the department list. Re-importing an
     * existing employee with a new email fills the email in.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $records = Csv::records($request->file('file'));

        if ($records === []) {
            return redirect()->route('admin.employees.index')
                ->with('error', __('The CSV file has no data rows.'));
        }

        $departments = Department::all()->keyBy(fn ($department) => mb_strtolower($department->name));
        $employees = Employee::all()->keyBy(fn ($employee) => $employee->department_id.'|'.mb_strtolower($employee->name));
        $created = 0;
        $updated = [];
        $skipped = [];
        $errors = [];

        foreach ($records as $row => $record) {
            $name = Csv::field($record, 'Employee Name', 'Name');
            $departmentName = Csv::field($record, 'Department', 'Department Name');
            $email = Csv::field($record, 'Email', 'Email Address');

            if ($name === '' || mb_strlen($name) > 255) {
                $errors[] = ['row' => $row, 'message' => __('Missing or invalid employee name')];

                continue;
            }

            $department = $departments->get(mb_strtolower($departmentName));

            if (! $department) {
                $errors[] = ['row' => $row, 'message' => __('Department ":name" was not found', ['name' => $departmentName ?: '(blank)'])];

                continue;
            }

            if ($email !== '' && (mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
                $errors[] = ['row' => $row, 'message' => __('Invalid email ":email"', ['email' => $email])];

                continue;
            }

            $label = "{$name} ({$department->name})";
            $key = $department->id.'|'.mb_strtolower($name);

            if ($existing = $employees->get($key)) {
                if ($email !== '' && $email !== $existing->email) {
                    $existing->update(['email' => $email]);
                    $updated[] = $label;
                } else {
                    $skipped[] = $label;
                }

                continue;
            }

            $employees->put($key, Employee::create([
                'department_id' => $department->id,
                'name' => $name,
                'email' => $email !== '' ? $email : null,
            ]));
            $created++;
        }

        return redirect()->route('admin.employees.index')
            ->with('import', ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors]);
    }
```

- [ ] **Step 5: Include the partial** in `resources/views/admin/employees/index.blade.php` directly after the `session('status')` block:

```blade
        @include('admin.partials.import', ['route' => 'admin.employees', 'columns' => 'Employee Name, Department, Email (optional)'])
```

- [ ] **Step 6: Run** `php artisan test --filter=EmployeeImportTest` — PASS; then `php artisan test` — all green.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php app/Http/Controllers/Admin/EmployeeController.php resources/views/admin/employees/index.blade.php tests/Feature/EmployeeImportTest.php
git commit -m "feat: add bulk CSV import for employees"
```

---

### Task 4: Company import

**Files:**
- Modify: `routes/web.php` (building-mode group)
- Modify: `app/Http/Controllers/Admin/CompanyController.php`
- Modify: `resources/views/admin/companies/index.blade.php`
- Test: `tests/Feature/CompanyImportTest.php`

**Interfaces:**
- Consumes: `Csv` (Task 1); partial + flash shape (Task 2).
- Produces: routes `admin.companies.template`, `admin.companies.import`.

- [ ] **Step 1: Write the failing test** `tests/Feature/CompanyImportTest.php`:

```php
<?php

use App\Models\Company;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('importing companies creates, updates contact emails, skips, and reports errors', function () {
    Company::create(['name' => 'Acme', 'contact_email' => null]);
    Company::create(['name' => 'Globex', 'contact_email' => 'g@x.test']);

    $csv = "Company Name,Contact Email\n"
        ."Initech,i@x.test\n"   // 2 created
        ."acme,a@x.test\n"      // 3 updated
        ."Globex,g@x.test\n"    // 4 skipped
        .",x@x.test\n"          // 5 error: name
        ."Umbrella,bad\n"       // 6 error: email
        ."initech,\n";          // 7 skipped (duplicate of row 2)

    $response = $this->actingAs($this->admin)->post('/admin/companies/import', ['file' => csvFile($csv)]);

    $response->assertRedirect('/admin/companies');
    $response->assertSessionHas('import', [
        'created' => 1,
        'updated' => ['acme'],
        'skipped' => ['Globex', 'initech'],
        'errors' => [
            ['row' => 5, 'message' => 'Missing or invalid company name'],
            ['row' => 6, 'message' => 'Invalid email "bad"'],
        ],
    ]);
    expect(Company::where('name', 'Acme')->first()->contact_email)->toBe('a@x.test');
    expect(Company::count())->toBe(3);
});

test('Name and Email columns are accepted as aliases', function () {
    $this->actingAs($this->admin)->post('/admin/companies/import', ['file' => csvFile("Name,Email\nHooli,h@x.test\n")]);

    expect(Company::where('name', 'Hooli')->first()->contact_email)->toBe('h@x.test');
});

test('company import is not available in company mode', function () {
    Setting::current()->update(['deployment_mode' => 'company']);

    $this->actingAs($this->admin)
        ->post('/admin/companies/import', ['file' => csvFile("Name\nHooli\n")])
        ->assertNotFound();
});

test('the template downloads with company headers', function () {
    $response = $this->actingAs($this->admin)->get('/admin/companies/template');

    $response->assertDownload('companies-template.csv');
    expect($response->streamedContent())->toContain('"Company Name","Contact Email"');
});

test('the companies page shows the import form', function () {
    $this->actingAs($this->admin)->get('/admin/companies')
        ->assertSee(route('admin.companies.import'), false);
});
```

If `Setting::current()` caches the row in a static and the mode-change test sees the stale mode, replace the update line with `Setting::query()->update(['deployment_mode' => 'company']);` plus whatever cache reset `ModeGuardedAdminRoutesTest` uses.

- [ ] **Step 2: Run** `php artisan test --filter=CompanyImportTest` — expected FAIL (404).

- [ ] **Step 3: Add routes** inside the `mode:building` group, above the companies resource line:

```php
        Route::get('companies/template', [CompanyController::class, 'template'])->name('companies.template');
        Route::post('companies/import', [CompanyController::class, 'import'])->name('companies.import');
```

- [ ] **Step 4: Add the actions** to `CompanyController` (add `use App\Support\Csv;` and `use Symfony\Component\HttpFoundation\StreamedResponse;`):

```php
    public function template(): StreamedResponse
    {
        return Csv::template('companies-template.csv', ['Company Name', 'Contact Email']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $records = Csv::records($request->file('file'));

        if ($records === []) {
            return redirect()->route('admin.companies.index')
                ->with('error', __('The CSV file has no data rows.'));
        }

        $companies = Company::all()->keyBy(fn ($company) => mb_strtolower($company->name));
        $created = 0;
        $updated = [];
        $skipped = [];
        $errors = [];

        foreach ($records as $row => $record) {
            $name = Csv::field($record, 'Company Name', 'Name', 'Company');
            $email = Csv::field($record, 'Contact Email', 'Email');

            if ($name === '' || mb_strlen($name) > 255) {
                $errors[] = ['row' => $row, 'message' => __('Missing or invalid company name')];

                continue;
            }

            if ($email !== '' && (mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
                $errors[] = ['row' => $row, 'message' => __('Invalid email ":email"', ['email' => $email])];

                continue;
            }

            if ($existing = $companies->get(mb_strtolower($name))) {
                if ($email !== '' && $email !== $existing->contact_email) {
                    $existing->update(['contact_email' => $email]);
                    $updated[] = $name;
                } else {
                    $skipped[] = $name;
                }

                continue;
            }

            $companies->put(mb_strtolower($name), Company::create([
                'name' => $name,
                'contact_email' => $email !== '' ? $email : null,
            ]));
            $created++;
        }

        return redirect()->route('admin.companies.index')
            ->with('import', ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors]);
    }
```

- [ ] **Step 5: Include the partial** in `resources/views/admin/companies/index.blade.php` directly after the `session('status')` block:

```blade
        @include('admin.partials.import', ['route' => 'admin.companies', 'columns' => 'Company Name, Contact Email (optional)'])
```

- [ ] **Step 6: Run** `php artisan test --filter=CompanyImportTest` — PASS; then `php artisan test` — all green.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php app/Http/Controllers/Admin/CompanyController.php resources/views/admin/companies/index.blade.php tests/Feature/CompanyImportTest.php
git commit -m "feat: add bulk CSV import for companies"
```

---

### Task 5: Admin tabs + single sidebar Admin link

**Files:**
- Create: `resources/views/components/admin-page.blade.php`
- Modify: `resources/views/admin/departments/index.blade.php`, `resources/views/admin/employees/index.blade.php`, `resources/views/admin/companies/index.blade.php`, `resources/views/admin/users/index.blade.php`, `resources/views/admin/settings/edit.blade.php`
- Modify: `resources/views/layouts/sidebar-nav.blade.php`
- Modify: `tests/Feature/NavigationTest.php`, `tests/Feature/SidebarLayoutTest.php`
- Test: `tests/Feature/AdminTabsTest.php`

**Interfaces:**
- Consumes: existing admin route names; `Setting::current()`.
- Produces: `<x-admin-page>` — wraps `<x-sidebar-layout>`, renders heading "Administration", tab `<nav aria-label="Administration">` with `aria-current="page"` on the active tab, and `session('status')` / `session('error')` flashes, then the slot.

- [ ] **Step 1: Write the failing test** `tests/Feature/AdminTabsTest.php`:

```php
<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('every company-mode admin page shows the same tab bar with the current tab marked', function (string $url) {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $response = $this->actingAs($this->admin)->get($url);

    $response->assertOk();
    $response->assertSee('Administration');
    $response->assertSee('href="'.route('admin.departments.index').'"', false);
    $response->assertSee('href="'.route('admin.employees.index').'"', false);
    $response->assertSee('href="'.route('admin.users.index').'"', false);
    $response->assertSee('href="'.route('admin.settings.edit').'"', false);
    $response->assertDontSee(route('admin.companies.index'), false);
    $response->assertSee('href="'.url($url).'" aria-current="page"', false);
})->with(['/admin/departments', '/admin/employees', '/admin/users', '/admin/settings']);

test('building-mode admin pages show companies, users, and settings tabs only', function (string $url) {
    Setting::create(['id' => 1, 'deployment_mode' => 'building']);

    $response = $this->actingAs($this->admin)->get($url);

    $response->assertOk();
    $response->assertSee('href="'.route('admin.companies.index').'"', false);
    $response->assertSee('href="'.route('admin.users.index').'"', false);
    $response->assertSee('href="'.route('admin.settings.edit').'"', false);
    $response->assertDontSee('/admin/departments', false);
    $response->assertDontSee('/admin/employees', false);
})->with(['/admin/companies', '/admin/users', '/admin/settings']);

test('flash messages render once through the admin page', function () {
    Setting::create(['id' => 1, 'deployment_mode' => 'company']);

    $response = $this->actingAs($this->admin)->withSession(['error' => 'Something failed'])->get('/admin/settings');

    expect(substr_count($response->getContent(), 'Something failed'))->toBe(1);
});
```

The active-tab assertion depends on attribute order: the component must render `href="..."` immediately followed by ` aria-current="page"`.

- [ ] **Step 2: Run** `php artisan test --filter=AdminTabsTest` — expected FAIL.

- [ ] **Step 3: Create** `resources/views/components/admin-page.blade.php`:

```blade
@php
    $mode = \App\Models\Setting::current()?->deployment_mode;
    $tabs = $mode === 'company'
        ? [['admin.departments.index', 'admin.departments.*', __('Departments')], ['admin.employees.index', 'admin.employees.*', __('Employees')]]
        : [['admin.companies.index', 'admin.companies.*', __('Companies')]];
    $tabs[] = ['admin.users.index', 'admin.users.*', __('Users')];
    $tabs[] = ['admin.settings.edit', 'admin.settings.*', __('Settings')];
@endphp

<x-sidebar-layout>
    <div class="mx-auto max-w-6xl px-4 py-6 md:px-8 md:py-10">
        <h1 class="text-headline-md text-on-surface">{{ __('Administration') }}</h1>

        <nav aria-label="{{ __('Administration') }}" class="mt-4 flex gap-1 overflow-x-auto border-b border-outline-variant">
            @foreach ($tabs as [$route, $pattern, $label])
                <a href="{{ route($route) }}"@if (request()->routeIs($pattern)) aria-current="page"@endif class="whitespace-nowrap border-b-2 px-4 py-2 text-label-md {{ request()->routeIs($pattern) ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface' }}">{{ $label }}</a>
            @endforeach
        </nav>

        @if (session('status'))
            <p class="mt-4 text-green-700" role="status">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="mt-4 text-red-600" role="alert">{{ session('error') }}</p>
        @endif

        <div class="mt-6 max-w-3xl">
            {{ $slot }}
        </div>
    </div>
</x-sidebar-layout>
```

Check the rendered output: `@if (...) aria-current="page"@endif` directly after the closing `"` of `href` must produce `href="URL" aria-current="page"`. If Blade inserts different whitespace, adjust the template (not the test) so the test's string matches.

- [ ] **Step 4: Convert the five admin views.** In each of `admin/departments/index`, `admin/employees/index`, `admin/companies/index`, `admin/users/index`, `admin/settings/edit`:
  - replace the opening `<x-sidebar-layout>` + `<div class="p-6 max-w-...">` with `<x-admin-page>`, and the closing `</div>` + `</x-sidebar-layout>` with `</x-admin-page>`;
  - delete the page's `<h1 ...>` line;
  - delete its `@if (session('status')) ... @endif` block, and in departments also the `@if (session('error')) ... @endif` block;
  - in `admin/settings/edit.blade.php` also delete the `@if (session('error')) ... @endif` block inside the test-email form (the component now shows it at the top).

  Example — `admin/departments/index.blade.php` after the change:

```blade
<x-admin-page>
    @include('admin.partials.import', ['route' => 'admin.departments', 'columns' => 'Department Name'])

    <form method="POST" action="{{ route('admin.departments.store') }}" class="flex gap-2 mb-6">
        @csrf
        <input type="text" name="name" placeholder="{{ __('Department name') }}" class="border rounded ps-3 pe-3 py-2 flex-1" required>
        <button type="submit" class="bg-primary text-on-primary rounded px-4 py-2">{{ __('Add') }}</button>
    </form>

    <ul>
        @foreach ($departments as $department)
            <li class="flex justify-between items-center border-b py-2">
                <span>{{ $department->name }} ({{ $department->employees_count }})</span>
                <form method="POST" action="{{ route('admin.departments.destroy', $department) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600">{{ __('Delete') }}</button>
                </form>
            </li>
        @endforeach
    </ul>
</x-admin-page>
```

- [ ] **Step 5: Collapse the sidebar admin group.** In `resources/views/layouts/sidebar-nav.blade.php`, replace the entire `@if ($isSidebar) ... @else ... @endif` inside `@role('admin')` with one link used by both variants:

```blade
    @role('admin')
        <a
            href="{{ $mode === 'company' ? route('admin.departments.index') : route('admin.companies.index') }}"
            class="{{ $isSidebar
                ? 'mt-4 flex items-center gap-3 rounded-lg px-4 py-3 text-label-md ' . (request()->routeIs('admin.*') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container')
                : 'flex flex-1 flex-col items-center gap-0.5 py-2 text-label-sm ' . (request()->routeIs('admin.*') ? 'text-primary' : 'text-on-surface-variant') }}"
        >
            <x-icon name="shield_person" /> {{ __('Admin') }}
        </a>
    @endrole
```

- [ ] **Step 6: Update the nav tests that asserted the old expandable group.**

In `tests/Feature/NavigationTest.php`, replace the company-mode admin test body's assertions with:

```php
    $response->assertSee(route('admin.departments.index'), false);
    $response->assertDontSee(route('admin.employees.index'), false);
    $response->assertDontSee(route('admin.users.index'), false);
    $response->assertDontSee(route('admin.companies.index'), false);
```

and rename it to `'an admin in company mode gets a single admin link to departments'`. The building-mode test stays as is.

In `tests/Feature/SidebarLayoutTest.php`, in the first test replace `assertSee('Departments')` / `assertSee('Employees')` / `assertDontSee('Companies')` with:

```php
    $response->assertSee(route('admin.departments.index'), false);
    $response->assertDontSee('Employees');
    $response->assertDontSee('Companies');
```

and rename it to `'an admin in company mode sees all nav sections with a single admin link'`. In the second (building) test replace `assertSee('Companies')` with `assertSee(route('admin.companies.index'), false)`, keeping its `assertDontSee` lines.

- [ ] **Step 7: Run** `php artisan test` — all green, including `AdminTabsTest`.

- [ ] **Step 8: Commit**

```bash
git add resources/views/components/admin-page.blade.php resources/views/admin resources/views/layouts/sidebar-nav.blade.php tests/Feature/AdminTabsTest.php tests/Feature/NavigationTest.php tests/Feature/SidebarLayoutTest.php
git commit -m "feat: present admin pages as tabs and collapse sidebar admin group"
```
