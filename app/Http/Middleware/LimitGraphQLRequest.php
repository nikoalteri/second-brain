<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects GraphQL requests that are too large or batched before Lighthouse parses them.
 *
 * Depth and complexity limits only apply after the document is parsed and validated, so a very
 * large body would already have cost CPU and memory by then. The SPA sends one operation per
 * request, so batching is not needed and would multiply the cost of a single request.
 */
class LimitGraphQLRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $maxBytes = (int) config('lighthouse.request_limits.max_bytes', 65536);

        if (strlen($request->getContent()) > $maxBytes) {
            return $this->error('The GraphQL request is too large.', 413);
        }

        $payload = $request->json()->all();

        if ($payload !== [] && array_is_list($payload)) {
            return $this->error('Batched GraphQL requests are not supported.', 400);
        }

        return $next($request);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['errors' => [['message' => $message]]], $status);
    }
}
