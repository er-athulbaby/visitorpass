<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeploymentMode
{
    public function handle(Request $request, Closure $next, string $mode): Response
    {
        abort_unless(Setting::current()?->deployment_mode === $mode, 404);

        return $next($request);
    }
}
