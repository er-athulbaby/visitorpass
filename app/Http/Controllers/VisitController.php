<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckOutRequest;
use App\Http\Requests\StoreVisitRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VisitController extends Controller
{
    public function index(): View
    {
        return view('visits.index', [
            'openVisits' => Visit::with('visitor', 'employee', 'company')
                ->whereNull('check_out_at')
                ->orderByDesc('check_in_at')
                ->get(),
        ]);
    }

    public function checkOut(CheckOutRequest $request, Visit $visit): RedirectResponse
    {
        if (! $visit->isOpen()) {
            return redirect()->route('visits.index')
                ->with('error', __('This visitor has already checked out.'));
        }

        $visit->update(['check_out_at' => now()]);

        return redirect()->route('visits.index')
            ->with('status', __('Visitor checked out.'));
    }

    public function create(): View
    {
        $mode = Setting::current()->deployment_mode;

        return view('visits.create', [
            'mode' => $mode,
            'employees' => $mode === 'company' ? Employee::with('department')->orderBy('name')->get() : collect(),
            'companies' => $mode === 'building' ? Company::orderBy('name')->get() : collect(),
        ]);
    }

    public function store(StoreVisitRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $visitor = Visitor::updateOrCreate(
            ['cpr_number' => $validated['cpr_number']],
            [
                'name' => $validated['name'],
                'company_name' => $validated['company_name'] ?? null,
                'mobile_number' => $validated['mobile_number'] ?? null,
            ]
        );

        $visitor->visits()->create([
            'mobile_number' => $validated['mobile_number'] ?? null,
            'employee_id' => $validated['employee_id'] ?? null,
            'department_id' => $request->departmentIdForEmployee(),
            'company_id' => $validated['company_id'] ?? null,
            'check_in_at' => now(),
        ]);

        return redirect()->route('visits.index')
            ->with('status', __('Visitor checked in.'));
    }
}
