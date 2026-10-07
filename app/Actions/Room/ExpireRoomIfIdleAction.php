<?php

namespace App\Actions\Room;

use App\Models\KaraokeQueue;
use App\Models\PlaybackSession;
use App\Models\Room;
use Illuminate\Support\Carbon;

class ExpireRoomIfIdleAction
{
    /**
     * Close the room if it has gone without karaoke for the idle window (no song added, nothing playing).
     *
     * Every way into a room runs this, so an expired room is refused on the spot instead of letting
     * someone in until the next cleanup pass closes it under them. Must run outside a transaction that
     * may roll back, or the close would be undone.
     */
    public static function execute(Room $room): bool
    {
        $cutoff = now()->subMinutes(self::idleMinutes());

        // Cheapest signals first: a new room, or a playing one (the host's heartbeat touches the
        // playback session every few seconds), is decided without looking at the queue
        if ($room->created_at->gte($cutoff)) {
            return false;
        }

        $playbackMovedAt = PlaybackSession::where('room_id', $room->id)->value('updated_at');
        if ($playbackMovedAt && Carbon::parse($playbackMovedAt)->gte($cutoff)) {
            return false;
        }

        $lastSongAddedAt = KaraokeQueue::where('room_id', $room->id)->max('created_at');
        if ($lastSongAddedAt && Carbon::parse($lastSongAddedAt)->gte($cutoff)) {
            return false;
        }

        // Anyone still connected gets room.closed with this reason
        CloseRoomAction::execute($room, self::message());

        return true;
    }

    public static function message(): string
    {
        return 'This party ended after ' . self::idleMinutes() . ' minutes without any songs.';
    }

    private static function idleMinutes(): int
    {
        return (int) config('karaoke.room_idle_minutes', 30);
    }
}
