<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $isSetupRoute = $request->is('setup');

        if ($request->is('install')) {
            return $next($request);
        }

        try {
            $isComplete = Setting::isComplete();
        } catch (Throwable) {
            return response()->view('errors.db-unreachable', [], 503);
        }

        if (! $isComplete && ! $isSetupRoute) {
            return redirect('/setup');
        }

        if ($isComplete && $isSetupRoute) {
            return redirect('/login');
        }

        return $next($request);
    }
}
