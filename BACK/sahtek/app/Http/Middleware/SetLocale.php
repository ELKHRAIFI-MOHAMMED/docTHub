<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Locale resolution order: X-Locale header → ?lang= → Accept-Language → fallback (en). */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('docthub.supported_locales', ['en']);

        $candidates = array_filter([
            $request->header('X-Locale'),
            $request->query('lang'),
            $request->getPreferredLanguage($supported),
        ]);

        $locale = config('app.fallback_locale', 'en');
        foreach ($candidates as $candidate) {
            $code = strtolower(substr((string) $candidate, 0, 2));
            if (in_array($code, $supported, true)) {
                $locale = $code;
                break;
            }
        }

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
