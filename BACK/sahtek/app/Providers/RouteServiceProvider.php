<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Brute-force protection on login/registration.
        RateLimiter::for('auth', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            ];
        });

        // Symptom checker writes (create/answer/evaluate).
        RateLimiter::for('assessments', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            // V1 legacy API (kept for the V1 frontend).
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // V2 API.
            Route::middleware(['api', 'locale'])
                ->prefix('api/v1')
                ->name('v1.')
                ->group(base_path('routes/api_v1.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
