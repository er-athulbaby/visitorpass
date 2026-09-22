<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Setting;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['search', 'date_from', 'date_to', 'department_id', 'company_id', 'status']);

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            // BOM so Excel renders Arabic (and other non-ASCII) names correctly
            // instead of mojibake when the file is double-clicked.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Visitor Name', 'CPR Number', 'Mobile Number', 'Company', 'Department', 'Person to Visit', 'Check-In', 'Check-Out', 'Status']);

            $safe = fn ($value) => preg_match('/^[=+\-@\t\r]/', (string) $value) ? "'".$value : $value;

            Visit::with('visitor', 'employee', 'company', 'department')
                ->filtered($filters)
                ->orderByDesc('check_in_at')
                ->lazy()
                ->each(function ($visit) use ($handle, $safe) {
                    fputcsv($handle, [
                        $safe($visit->visitor->name),
                        $visit->visitor->cpr_number,
                        $visit->mobile_number ?? $visit->visitor->mobile_number,
                        $safe($visit->visitor->company_name ?? ''),
                        $visit->department?->name ?? '',
                        $safe($visit->employee?->name ?? $visit->company?->name ?? ''),
                        $visit->check_in_at->toIso8601String(),
                        $visit->check_out_at?->toIso8601String() ?? '',
                        $visit->isOpen() ? 'INSIDE' : 'CHECKED_OUT',
                    ]);
                });

            fclose($handle);
        }, 'visitor-history-'.now()->timestamp.'.csv', ['Content-Type' => 'text/csv']);
    }
}
