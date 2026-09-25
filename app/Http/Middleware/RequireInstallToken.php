<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Before the first admin exists, /install and /setup are open to anyone who
 * finds the URL. When INSTALL_TOKEN is set, they answer 404 unless the request
 * carries that token, so only the person deploying can finish the wizard.
 */
class RequireInstallToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('app.install_token');

        abort_if($token !== '' && ! hash_equals($token, (string) $request->input('token')), 404);

        return $next($request);
    }
}
