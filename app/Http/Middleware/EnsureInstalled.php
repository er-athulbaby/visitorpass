<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $isInstallRoute = $request->is('install');

        if (Storage::disk('local')->exists('installed')) {
            abort_if($isInstallRoute, 404);

            return $next($request);
        }

        // Until install finishes the DB credentials are placeholders, so keep
        // sessions and cache (the install form's rate limiter) off the database.
        Config::set('session.driver', 'file');
        Config::set('cache.default', 'file');

        if (empty(config('app.key'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }

        try {
            DB::connection()->getPdo();
            $connected = true;
        } catch (Throwable) {
            $connected = false;
        }

        if ($connected && Schema::hasTable('settings')) {
            Storage::disk('local')->put('installed', now()->toString());
            abort_if($isInstallRoute, 404);

            return $next($request);
        }

        if ($isInstallRoute) {
            return $next($request);
        }

        return redirect('/install');
    }
}
