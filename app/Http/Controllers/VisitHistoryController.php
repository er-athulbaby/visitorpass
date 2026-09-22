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
