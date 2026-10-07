<?php

namespace App\Http\Middleware;

use App\Models\Room;
use App\Models\RoomMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureQueueReorder
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

        $allowGuestReorder = (bool) (
            $room->settings['allow_guest_reorder'] ?? 
            $room->settings['allowGuestReorder'] ?? 
            false
        );

        if (!$member->isHost() && !$allowGuestReorder) {
            return response()->json([
                'success' => false,
                'message' => 'Queue reordering is restricted to the room host.',
                'code' => 'HOST_PERMISSION_REQUIRED',
                'errors' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
