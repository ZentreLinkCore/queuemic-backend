<?php

namespace App\Actions\Playback;

use App\Models\Room;
use App\Models\KaraokeQueue;
use App\Models\ActivityLog;
use App\Models\RoomMember;
use Illuminate\Support\Facades\DB;

class SkipCurrentSongAction
{
    public static function execute(Room $room, ?RoomMember $host = null): ?array
    {
        return DB::transaction(function () use ($room, $host) {
            $currentPlaying = KaraokeQueue::where('room_id', $room->id)
                ->where('status', 'playing')
                ->first();

            if ($currentPlaying) {
                $currentPlaying->update([
                    'status' => 'skipped',
                    'finished_at' => now(),
                ]);

                ActivityLog::create([
                    'room_id' => $room->id,
                    'room_member_id' => $host?->id,
                    'action' => 'song_skipped',
                    'payload' => ['song_title' => $currentPlaying->song?->title],
                ]);
            }

            return StartNextSongAction::execute($room);
        });
    }
}
