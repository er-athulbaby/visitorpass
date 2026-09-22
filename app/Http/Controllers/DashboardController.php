<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $startOfToday = now()->startOfDay();

        return view('dashboard', [
            'totalToday' => Visit::where('check_in_at', '>=', $startOfToday)->count(),
            'currentlyInside' => Visit::whereNull('check_out_at')->count(),
            'checkedOutToday' => Visit::whereNotNull('check_out_at')
                ->where('check_out_at', '>=', $startOfToday)
                ->count(),
            'newVisitorsToday' => Visitor::where('created_at', '>=', $startOfToday)->count(),
            'recentVisits' => Visit::with('visitor', 'employee', 'company')
                ->orderByDesc('check_in_at')
                ->limit(5)
                ->get(),
            'mode' => Setting::current()->deployment_mode,
        ]);
    }
}
