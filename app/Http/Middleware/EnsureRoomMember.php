<?php

namespace App\Http\Middleware;

use App\Actions\Room\ExpireRoomIfIdleAction;
use App\Models\Room;
use App\Models\RoomMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoomMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $roomCode = $request->route('code') ?? $request->input('room_code');

        if (!$roomCode) {
            return response()->json([
                'success' => false,
                'message' => 'Room code is required.',
                'code' => 'ROOM_CODE_REQUIRED',
                'errors' => (object) [],
            ], 400);
        }

        $room = Room::where('code', strtoupper($roomCode))->where('status', 'active')->first();

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Room not found or closed',
                'code' => 'ROOM_NOT_FOUND',
                'errors' => (object) [],
            ], 404);
        }

        // Idle past the expiry window: close it now instead of letting anyone in until the next cleanup pass
        if (ExpireRoomIfIdleAction::execute($room)) {
            return response()->json([
                'success' => false,
                'message' => ExpireRoomIfIdleAction::message(),
                'code' => 'ROOM_NOT_FOUND',
                'errors' => (object) [],
            ], 404);
        }

        $token = $request->bearerToken() ?? $request->header('X-Session-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication token required.',
                'code' => 'UNAUTHENTICATED',
                'errors' => (object) [],
            ], 401);
        }

        $tokenHash = hash('sha256', $token);
        $member = RoomMember::where('session_token_hash', $tokenHash)
            ->where('room_id', $room->id)
            ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session or member not in this room.',
                'code' => 'INVALID_SESSION',
                'errors' => (object) [],
            ], 403);
        }

        // Any request counts as presence (host-away expiry reads the host seat); written at most once a minute
        if (!$member->last_seen_at || $member->last_seen_at->lt(now()->subMinute())) {
            $member->update(['last_seen_at' => now()]);
        }

        // Attach resolved room and member to request attributes
        $request->attributes->set('room', $room);
        $request->attributes->set('member', $member);

        return $next($request);
    }
}
