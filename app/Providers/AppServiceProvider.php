<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $by = fn (Request $request) => (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

        // Visitor lookups fire while typing a CPR: generous for a busy desk,
        // far too slow to scrape every visitor's CPR and mobile number.
        RateLimiter::for('lookup', fn (Request $request) => Limit::perMinute(120)->by($by($request)));
        // Heavy or outward actions: CSV export, bulk imports, test email.
        RateLimiter::for('heavy', fn (Request $request) => Limit::perMinute(10)->by($by($request)));
        // Forms anyone can reach without signing in.
        RateLimiter::for('guest-forms', fn (Request $request) => Limit::perMinute(6)->by((string) $request->ip()));
    }
}
