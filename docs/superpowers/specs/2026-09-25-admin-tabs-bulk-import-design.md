# Admin Tabs + Bulk CSV Import — Design (UI sub-project 5)

## Goal

1. Present the admin pages as one tabbed "Administration" section, matching the
   original React app, and collapse the sidebar's expandable Admin group into a
   single link (as planned in the UI-shell spec).
2. Add bulk CSV import to Departments, Employees, and Companies.

## Tabs

- Existing URLs, controllers, and route names stay unchanged. Each tab is its
  own page load; no JS.
- New anonymous component `resources/views/components/admin-page.blade.php`:
  page container (`mx-auto max-w-6xl px-4 py-6 md:px-8 md:py-10`),
  "Administration" heading, tab bar, `session('status')` / `session('error')`
  flash messages, then `{{ $slot }}`. It wraps `<x-sidebar-layout>`.
- Tab bar (mode-aware, active tab by `request()->routeIs`):
  - company mode: Departments, Employees, Users, Settings
  - building mode: Companies, Users, Settings
- The 5 admin views (`admin/{departments,employees,companies,users}/index`,
  `admin/settings/edit`) switch to `<x-admin-page>` and drop their own
  container/heading/flash markup. Their forms are otherwise untouched.
- Sidebar (`layouts/sidebar-nav.blade.php`): the expandable Admin group becomes
  one "Admin" link (`shield_person` icon) to the first tab (Departments in
  company mode, Companies in building mode), active on `admin.*` — same target
  the mobile bottom nav already uses.
- Settings stays a single tab (branding + SMTP together); no split into a
  separate Email tab.

## Bulk import

### Routes (admin group, inside each entity's existing mode middleware)

| Method | URI | Name |
|---|---|---|
| GET | admin/departments/template | admin.departments.template |
| POST | admin/departments/import | admin.departments.import |
| GET | admin/employees/template | admin.employees.template |
| POST | admin/employees/import | admin.employees.import |
| GET | admin/companies/template | admin.companies.template |
| POST | admin/companies/import | admin.companies.import |

Actions `template()` and `import()` are added to the existing
`DepartmentController`, `EmployeeController`, `CompanyController`.

### CSV parsing — `App\Support\Csv`

Plain PHP `fgetcsv`, no new dependency.

- `Csv::records(UploadedFile $file): array` — first row is the header; strips a
  UTF-8 BOM; header names trimmed and lower-cased; fully blank rows skipped;
  returns a list of `['header' => 'value']` arrays (values trimmed).
- `Csv::field(array $record, string ...$names): string` — first matching column
  (case-insensitive), `''` if none.
- `Csv::template(string $filename, array $headers): StreamedResponse` — BOM +
  header row, `text/csv`.

### Upload validation

`file` → `required|file|mimes:csv,txt|max:2048`. No data rows →
`back()->with('error', 'The CSV file has no data rows.')`.

### Row rules (row number = index + 2, i.e. the spreadsheet line)

**Departments** — column `Department Name` / `Name` / `Department`.
- blank or > 255 chars → error "Missing or invalid department name"
- name matches an existing department (case-insensitive, including ones created
  earlier in the same file) → skipped
- else → created

**Employees** — columns `Employee Name` / `Name`, `Department` /
`Department Name`, optional `Email` / `Email Address`.
- blank or > 255 name → error "Missing or invalid employee name"
- department not found (case-insensitive) → error `Department "X" was not found`
  (departments are never auto-created, so typos don't fragment the list)
- email present but invalid (`filter_var` `FILTER_VALIDATE_EMAIL`) → error
  `Invalid email "X"`
- same name (case-insensitive) already in that department → updated if the row
  has an email different from the stored one, else skipped
- else → created

**Companies** — columns `Company Name` / `Name` / `Company`, optional
`Contact Email` / `Email`.
- same rules as Employees minus the department; matches on name
  (case-insensitive); `contact_email` is the updatable field.

Rows are processed independently (no wrapping transaction): good rows land
even when others error, same as the reference app.

### Result

`back()->with('import', ['created' => int, 'updated' => string[], 'skipped' =>
string[], 'errors' => [['row' => int, 'message' => string]]])`.

### UI — `resources/views/admin/partials/import.blade.php`

Included on the three entity tabs with `route` prefix and a label. Card with:
"Download template" link, file input (`accept=".csv"`), "Import" button
(multipart POST). When `session('import')` is set, a summary under it: created
count, updated / skipped name lists, and an error list as "Row N: message".
Validation errors for `file` shown inline.

## Tests (Pest, feature)

Per entity: create, skip (existing + duplicate in file), update (employees,
companies), each error kind, empty file, non-admin gets 403, template download
headers. Tabs: each admin page shows the mode's tab links; sidebar renders a
single Admin link and no per-entity admin links; building mode shows no
Departments/Employees tabs.

## Non-goals

- Active/inactive status toggle on departments/employees/companies/users.
- Import for Users or Settings.
- Editing existing records (only add/delete/import, as today).
- Background/queued imports; file size capped at 2 MB instead.
