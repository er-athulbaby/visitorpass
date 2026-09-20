<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
