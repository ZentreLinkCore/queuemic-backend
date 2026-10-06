<?php

namespace App\Actions\Playback;

use App\Models\Room;
use App\Models\PlaybackSession;
use App\Events\PlaybackStateChangedEvent;
use Illuminate\Support\Facades\DB;

class UpdatePlaybackStateAction
{
    public static function execute(
        Room $room,
        string $state,
        float $currentTimeSeconds = 0,
        ?int $queueItemId = null,
        ?int $positionAtMs = null
    ): bool {
        return DB::transaction(function () use ($room, $state, $currentTimeSeconds, $queueItemId, $positionAtMs) {
            $playback = PlaybackSession::firstOrCreate(['room_id' => $room->id]);

            // Drop late updates for a song that is no longer on stage (e.g. a heartbeat in flight during a skip)
            if ($queueItemId !== null && $playback->queue_item_id !== null && (int) $playback->queue_item_id !== $queueItemId) {
                return false;
            }

            // touch() so updated_at always marks when this position was true (late joiners extrapolate from it)
            $playback->fill([
                'state' => $state,
                'current_time_seconds' => (int) round($currentTimeSeconds),
            ])->touch();

            try {
                broadcast(new PlaybackStateChangedEvent(
                    $room,
                    $state,
                    $currentTimeSeconds,
                    $playback->queue_item_id,
                    $positionAtMs ?? (int) floor(microtime(true) * 1000)
                ));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Playback state broadcast failed: ' . $e->getMessage());
            }

            return true;
        });
    }
}
