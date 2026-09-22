<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class VisitHistoryController extends Controller
{
    public function index(): View
    {
        return view('history.index');
    }
}
