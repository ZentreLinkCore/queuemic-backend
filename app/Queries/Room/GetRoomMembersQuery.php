<?php

namespace App\Queries\Room;

use App\Models\Room;
use App\Models\RoomMember;
use Illuminate\Support\Collection;

class GetRoomMembersQuery
{
    public static function execute(string $code): Collection
    {
        $room = Room::where('code', strtoupper($code))->where('status', 'active')->first();

        if (!$room) {
            return collect();
        }

        // Members who exited are kept (their seat is reused on rejoin) but are not listed
        return RoomMember::where('room_id', $room->id)
            ->where('is_connected', true)
            ->orderByRaw("CASE WHEN role = 'host' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'room_id' => $member->room_id,
                    'nickname' => $member->nickname,
                    'avatar_color' => $member->avatar_color,
                    'role' => $member->role,
                    'is_connected' => (bool) $member->is_connected,
                    'last_seen_at' => $member->last_seen_at?->toISOString() ?? now()->toISOString(),
                ];
            });
    }
}
