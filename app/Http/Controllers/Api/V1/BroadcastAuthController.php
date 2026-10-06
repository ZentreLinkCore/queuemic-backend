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
}
