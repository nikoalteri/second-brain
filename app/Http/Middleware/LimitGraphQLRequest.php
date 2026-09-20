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
 *
 * Lighthouse's route accepts GET as well as POST (query/variables read from the query string
 * instead of the body), so the size check must measure the query string on a GET request —
 * getContent() is empty for GET and would let an arbitrarily large query/variables pair through.
 */
class LimitGraphQLRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $maxBytes = (int) config('lighthouse.request_limits.max_bytes', 65536);
        $isGet = $request->isMethod('GET');

        $size = $isGet
            ? strlen(http_build_query($request->query()))
            : strlen($request->getContent());

        if ($size > $maxBytes) {
            return $this->error('The GraphQL request is too large.', 413);
        }

        $payload = $isGet ? $request->query() : $request->json()->all();

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
