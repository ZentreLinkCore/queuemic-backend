<?php

namespace App\Http\Middleware;

use App\Models\RoomMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoomHost
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var RoomMember|null $member */
        $member = $request->attributes->get('member');

        if (!$member || !$member->isHost()) {
            return response()->json([
                'success' => false,
                'message' => 'Only the room host can control playback and room settings.',
                'code' => 'HOST_PERMISSION_REQUIRED',
                'errors' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
