<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('role:admin') or 'role:patient,admin'. Must run after auth:sanctum. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'This account is disabled.'], 403);
        }

        if (! $user->hasRole(...$roles)) {
            return response()->json(['message' => 'You are not allowed to perform this action.'], 403);
        }

        return $next($request);
    }
}
