<?php

namespace App\Queries\Queue;

use App\Models\Room;
use App\Models\KaraokeQueue;
use Illuminate\Support\Collection;

class GetRoomQueueQuery
{
    public static function execute(string $code): Collection
    {
        $room = Room::where('code', strtoupper($code))->where('status', 'active')->first();

        if (!$room) {
            return collect();
        }

        return KaraokeQueue::with(['song', 'requestedBy'])
            ->where('room_id', $room->id)
            ->where('status', 'pending')
            ->orderBy('position', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'room_id' => $item->room_id,
                    'song_id' => $item->song_id,
                    'requested_by_member_id' => $item->requested_by_member_id,
                    'singer_name' => $item->singer_name,
                    'position' => $item->position,
                    'status' => $item->status,
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
            });
    }
}
