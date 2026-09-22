<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckOutRequest;
use App\Http\Requests\StoreVisitRequest;
use App\Mail\VisitorCheckedIn;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Visit;
use App\Models\Visitor;
use App\Services\NotificationMailer;
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

        $visitorUpdates = array_filter(
            [
                'name' => $validated['name'],
                'company_name' => $validated['company_name'] ?? null,
                'mobile_number' => $validated['mobile_number'] ?? null,
            ],
            fn ($value) => $value !== null
        );

        $visitor = Visitor::firstOrNew(['cpr_number' => $validated['cpr_number']]);
        $visitor->fill($visitorUpdates)->save();

        $visit = $visitor->visits()->create([
            'mobile_number' => $validated['mobile_number'] ?? null,
            'employee_id' => $validated['employee_id'] ?? null,
            'department_id' => $request->departmentIdForEmployee(),
            'company_id' => $validated['company_id'] ?? null,
            'check_in_at' => now(),
        ]);

        $mode = Setting::current()->deployment_mode;

        $recipient = match ($mode) {
            'company' => Employee::find($validated['employee_id'] ?? null)?->email,
            'building' => Company::find($validated['company_id'] ?? null)?->contact_email,
            default => null,
        };

        if (! empty($recipient)) {
            $visit->load('visitor', 'employee', 'company');
            app(NotificationMailer::class)->send(
                Setting::current(),
                new VisitorCheckedIn($visit),
                $recipient
            );
        }

        return redirect()->route('visits.index')
            ->with('status', __('Visitor checked in.'));
    }
}
