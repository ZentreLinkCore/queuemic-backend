<?php

namespace App\Queries\Queue;

use App\Models\Room;
use App\Models\KaraokeQueue;

class GetCurrentPlayingQuery
{
    public static function execute(string $code): ?array
    {
        $room = Room::where('code', strtoupper($code))->where('status', 'active')->first();

        if (!$room) {
            return null;
        }

        $item = KaraokeQueue::with(['song', 'requestedBy'])
            ->where('room_id', $room->id)
            ->where('status', 'playing')
            ->first();

        if (!$item) {
            return null;
        }

        return [
            'id' => $item->id,
            'room_id' => $item->room_id,
            'song_id' => $item->song_id,
            'requested_by_member_id' => $item->requested_by_member_id,
            'singer_name' => $item->singer_name,
            'position' => $item->position,
            'status' => $item->status,
            'played_at' => $item->played_at?->toISOString(),
            'song' => [
                'id' => $item->song->id,
                'external_video_id' => $item->song->external_video_id,
                'title' => $item->song->title,
                'artist' => $item->song->artist,
                'duration_formatted' => $item->song->duration_formatted,
                'duration_seconds' => $item->song->duration_seconds,
                'thumbnail_url' => $item->song->thumbnail_url,
                'source_provider' => $item->song->source_provider,
            ],
        ];
    }
}
