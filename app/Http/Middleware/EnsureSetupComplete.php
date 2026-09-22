<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $isSetupRoute = $request->is('setup');

        if ($request->is('install')) {
            return $next($request);
        }

        if (! Setting::isComplete() && ! $isSetupRoute) {
            return redirect('/setup');
        }

        if (Setting::isComplete() && $isSetupRoute) {
            return redirect('/login');
        }

        return $next($request);
    }
}
