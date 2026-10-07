<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RoomMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class BroadcastAuthController extends Controller
{
    /**
     * Authenticate the incoming WebSocket channel request.
     */
    public function authenticate(Request $request)
    {
        // Extract session token from header or body
        $token = $request->bearerToken() 
            ?? $request->header('X-Session-Token') 
            ?? $request->input('token')
            ?? $request->header('Authorization');

        if ($token && str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        try {
            if ($token) {
                $tokenHash = hash('sha256', trim($token));
                $member = RoomMember::with('room')->where('session_token_hash', $tokenHash)->first();
                if ($member) {
                    $request->setUserResolver(fn () => $member);
                }
            }

            return Broadcast::auth($request);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Unauthorized to access this broadcast channel.',
                'error' => 'Broadcast authorization failed.',
            ], 403);
        }
    }

    /**
     * Return public broadcasting config for mobile / web clients.
     */
    public function config()
    {
        // Apps connect to whichever broadcaster BROADCAST_CONNECTION selects, so switching between
        // Reverb and Pusher is a backend .env change only
        if (config('broadcasting.default') === 'pusher') {
            $pusher = config('broadcasting.connections.pusher');

            return response()->json([
                'broadcaster' => 'pusher',
                'key' => $pusher['key'],
                'cluster' => $pusher['options']['cluster'] ?? 'mt1',
                'useTLS' => true,
            ]);
        }

        $app = config('reverb.apps.apps.0', []);
        $options = $app['options'] ?? [];

        $host = $options['host'] ?? env('REVERB_HOST', 'localhost');
        $port = (int) ($options['port'] ?? env('REVERB_PORT', 8082));
        $scheme = $options['scheme'] ?? env('REVERB_SCHEME', 'http');
        $key = $app['key'] ?? env('REVERB_APP_KEY', '2n9ub0vuljc3wvqv0tgl');

        return response()->json([
            'broadcaster' => 'reverb',
            'key' => $key,
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'useTLS' => ($scheme === 'https' || $port === 443),
        ]);
    }
}
