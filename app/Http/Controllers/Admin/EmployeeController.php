<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function index(): View
    {
        return view('admin.employees.index', [
            'employees' => Employee::with('department')->orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        Employee::create($validated);

        return redirect()->route('admin.employees.index')
            ->with('status', __('Employee created.'));
    }

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

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()->route('admin.employees.index')
            ->with('status', __('Employee deleted.'));
    }
}
