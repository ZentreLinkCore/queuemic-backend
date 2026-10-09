<?php

namespace App\Actions\Playback;

use App\Models\Room;
use App\Models\PlaybackSession;
use App\Events\PlaybackStateChangedEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdatePlaybackStateAction
{
    // updated_at has 1s precision and positions are stored as whole seconds, so smaller gaps are noise
    private const SEEK_THRESHOLD_SECONDS = 3;

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

            $shouldBroadcast = self::shouldBroadcast($playback, $state, $currentTimeSeconds);

            // touch() so updated_at always marks when this position was true (late joiners extrapolate from it)
            $playback->fill([
                'state' => $state,
                'current_time_seconds' => (int) round($currentTimeSeconds),
            ])->touch();

            // Position beats from the host arrive every few seconds; guests extrapolate between them, so only
            // real changes plus one slow beat per window are broadcast (each one is billed per subscriber)
            $windowKey = "playback-broadcast:{$room->id}:{$playback->queue_item_id}";
            $window = (int) config('karaoke.playback_broadcast_window_seconds', 10);
            if ($shouldBroadcast) {
                Cache::put($windowKey, true, $window);
            } elseif (!Cache::add($windowKey, true, $window)) {
                return true;
            }

            try {
                broadcast(new PlaybackStateChangedEvent(
                    $room,
                    $state,
                    $currentTimeSeconds,
                    $playback->queue_item_id,
                    $positionAtMs ?? (int) floor(microtime(true) * 1000)
                ))->toOthers();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Playback state broadcast failed: ' . $e->getMessage());
            }

            return true;
        });
    }

    // A state change or a jump from where the previous report says playback should be (a seek) must reach guests now
    private static function shouldBroadcast(PlaybackSession $playback, string $state, float $currentTimeSeconds): bool
    {
        if ($playback->wasRecentlyCreated || $playback->state !== $state) {
            return true;
        }

        $expected = (float) $playback->current_time_seconds;
        if ($playback->state === 'playing' && $playback->updated_at) {
            $expected += max(0, now()->diffInSeconds($playback->updated_at, true));
        }

        return abs($currentTimeSeconds - $expected) > self::SEEK_THRESHOLD_SECONDS;
    }
}
