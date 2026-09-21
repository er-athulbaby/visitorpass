<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorLookupController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $cpr = $request->query('cpr');

        if (! $cpr) {
            return response()->json([]);
        }

        $visitor = Visitor::where('cpr_number', $cpr)->first();

        if (! $visitor) {
            return response()->json([]);
        }

        return response()->json([
            'name' => $visitor->name,
            'company_name' => $visitor->company_name,
            'mobile_number' => $visitor->mobile_number,
        ]);
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $query = $request->query('q');

        if (! $query) {
            return response()->json([]);
        }

        $visitors = Visitor::where('cpr_number', 'like', $query . '%')
            ->orderBy('cpr_number')
            ->limit(8)
            ->get(['id', 'cpr_number', 'name']);

        return response()->json($visitors);
    }
}
