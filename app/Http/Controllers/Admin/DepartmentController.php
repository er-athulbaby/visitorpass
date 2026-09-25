<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('admin.departments.index', [
            'departments' => Department::withCount('employees')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Department::create($validated);

        return redirect()->route('admin.departments.index')
            ->with('status', __('Department created.'));
    }

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

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->employees()->exists()) {
            return redirect()->route('admin.departments.index')
                ->with('error', __('Cannot delete a department that still has employees. Move or remove them first.'));
        }

        $department->delete();

        return redirect()->route('admin.departments.index')
            ->with('status', __('Department deleted.'));
    }
}
