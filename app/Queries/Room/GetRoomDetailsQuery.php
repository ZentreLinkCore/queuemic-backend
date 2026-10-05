<?php

namespace App\Queries\Room;

use App\Models\Room;

class GetRoomDetailsQuery
{
    public static function execute(string $code): ?array
    {
        $room = Room::with(['host', 'playbackSession.song', 'playbackSession.queueItem'])
            ->where('code', strtoupper($code))
            ->where('status', 'active')
            ->first();

        if (!$room) {
            return null;
        }

        return [
            'id' => $room->id,
            'code' => $room->code,
            'name' => $room->name,
            'host_member_id' => $room->host_member_id,
            'status' => $room->status,
            'settings' => $room->settings,
            'created_at' => $room->created_at->toISOString(),
            'current_playback' => $room->playbackSession ? [
                'state' => $room->playbackSession->state,
                'current_time_seconds' => $room->playbackSession->current_time_seconds,
                'current_song' => $room->playbackSession->song,
                'current_singer' => $room->playbackSession->queueItem?->singer_name,
            ] : null,
        ];
    }
}
