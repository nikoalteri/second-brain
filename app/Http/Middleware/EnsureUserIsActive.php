<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects API and GraphQL requests from deactivated users and drops their tokens.
 *
 * Deactivation already revokes tokens through the User model; this covers changes made
 * without model events (bulk updates, direct SQL) and tokens issued around the change.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        // Strict comparison: only an explicit deactivation counts, an unset attribute does not.
        if ($user !== null && $user->is_active === false) {
            $user->tokens()->delete();

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
