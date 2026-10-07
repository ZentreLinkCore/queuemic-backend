<?php

namespace App\Http\Middleware;

use App\Models\Room;
use App\Models\RoomMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlaybackControl
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var RoomMember|null $member */
        $member = $request->attributes->get('member');
        /** @var Room|null $room */
        $room = $request->attributes->get('room');

        if (!$member || !$room) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized room access.',
                'code' => 'UNAUTHORIZED',
                'errors' => (object) [],
            ], 403);
        }

        $allowGuestPlayback = (bool) ($room->settings['allow_guest_playback_control'] ?? false);

        if (!$member->isHost() && !$allowGuestPlayback) {
            return response()->json([
                'success' => false,
                'message' => 'Only the room host can control playback',
                'code' => 'HOST_PERMISSION_REQUIRED',
                'errors' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
