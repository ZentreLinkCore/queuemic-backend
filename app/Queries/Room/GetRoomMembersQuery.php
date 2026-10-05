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

        return RoomMember::where('room_id', $room->id)
            ->orderByRaw("FIELD(role, 'host', 'guest')")
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'room_id' => $member->room_id,
                    'nickname' => $member->nickname,
                    'avatar_color' => $member->avatar_color,
                    'role' => $member->role,
                    'is_connected' => $member->is_connected,
                    'last_seen_at' => $member->last_seen_at->toISOString(),
                ];
            });
    }
}
