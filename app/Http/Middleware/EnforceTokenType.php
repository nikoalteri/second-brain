<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps access and refresh tokens apart.
 *
 * A refresh token carries the literal `refresh` ability and is accepted only by the refresh
 * endpoint; every other token (access, or a session-backed transient token) is accepted
 * everywhere except there. The check is literal on purpose: Sanctum's tokenCan('refresh')
 * would also be satisfied by an access token holding the wildcard ability.
 */
class EnforceTokenType
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');
        $token = $user?->currentAccessToken();

        if ($token === null) {
            return $next($request);
        }

        $isRefreshToken = in_array('refresh', (array) ($token->abilities ?? []), true);
        $isRefreshEndpoint = $request->is('api/v1/auth/refresh');

        if ($isRefreshToken !== $isRefreshEndpoint) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
