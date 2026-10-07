<?php

namespace App\Actions\Room;

use App\Models\Room;
use App\Models\RoomMember;
use Illuminate\Support\Carbon;

class ExpireRoomIfIdleAction
{
    /**
     * Close the room once its host has been away for the expiry window. The host seat's last_seen_at is kept
     * fresh by the host app's heartbeat and by any of its requests; songs and guests do not keep a room alive.
     *
     * Every way into a room runs this, so an expired room is refused on the spot instead of letting
     * someone in until the next cleanup pass closes it under them. Must run outside a transaction that
     * may roll back, or the close would be undone.
     */
    public static function execute(Room $room): bool
    {
        $cutoff = now()->subMinutes(self::awayMinutes());

        // Cheapest signal first: every seat starts at or after the room's creation, so a young room has a present host
        if ($room->created_at->gte($cutoff)) {
            return false;
        }

        $hostLastSeenAt = RoomMember::where('id', $room->host_member_id)->value('last_seen_at');
        if ($hostLastSeenAt && Carbon::parse($hostLastSeenAt)->gte($cutoff)) {
            return false;
        }

        // Anyone still connected gets room.closed with this reason
        CloseRoomAction::execute($room, self::message());

        return true;
    }

    public static function message(): string
    {
        return 'This party ended because the host has been away for ' . self::awayMinutes() . ' minutes.';
    }

    private static function awayMinutes(): int
    {
        return (int) config('karaoke.host_away_minutes', 30);
    }
}
