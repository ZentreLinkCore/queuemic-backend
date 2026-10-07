<?php

namespace App\Http\Middleware;

use App\Actions\Room\CleanupInactiveRoomsAction;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the room cleanup off normal API traffic, so no scheduler process is needed:
 * at most once per interval, after the response has been sent.
 */
class CleanupInactiveRooms
{
    private const INTERVAL_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // File store on purpose: a cheap local check on every request instead of a DB round trip.
        // add() only succeeds once per interval, so exactly one request does the work.
        if (!Cache::store('file')->add('rooms:cleanup:last-run', true, now()->addMinutes(self::INTERVAL_MINUTES))) {
            return;
        }

        try {
            CleanupInactiveRoomsAction::execute();
        } catch (\Throwable $e) {
            Log::warning('Room cleanup failed: ' . $e->getMessage());
        }
    }
}
