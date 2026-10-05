<?php

namespace App\Actions\Playback;

use App\Models\Room;
use App\Models\PlaybackSession;
use App\Events\PlaybackStateChangedEvent;
use Illuminate\Support\Facades\DB;

class UpdatePlaybackStateAction
{
    public static function execute(Room $room, string $state, int $currentTimeSeconds = 0): bool
    {
        return DB::transaction(function () use ($room, $state, $currentTimeSeconds) {
            $playback = PlaybackSession::firstOrCreate(['room_id' => $room->id]);

            $playback->update([
                'state' => $state,
                'current_time_seconds' => $currentTimeSeconds,
            ]);

            broadcast(new PlaybackStateChangedEvent($room, $state, $currentTimeSeconds))->toOthers();

            return true;
        });
    }
}
