<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * State of the scheduler, from the heartbeat that a scheduled task writes every minute.
 * Unauthenticated on purpose so an external uptime monitor can poll it: it exposes only the
 * state and the age of the last run. 503 when the heartbeat is missing or older than 5 minutes.
 */
class SchedulerHealthController extends Controller
{
    private const MAX_AGE_SECONDS = 300;

    public function __invoke(): JsonResponse
    {
        $last = Cache::get('scheduler:heartbeat');
        $age = $last === null ? null : max(0, now()->timestamp - (int) $last);
        $ok = $age !== null && $age <= self::MAX_AGE_SECONDS;

        return response()->json(['status' => $ok ? 'ok' : 'stale', 'age_seconds' => $age], $ok ? 200 : 503);
    }
}
