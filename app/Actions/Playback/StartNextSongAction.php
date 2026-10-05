<?php

namespace App\Actions\Playback;

use App\Models\Room;
use App\Models\KaraokeQueue;
use App\Models\PlaybackSession;
use App\Events\SongStartedEvent;
use App\Events\QueueUpdatedEvent;
use App\Queries\Queue\GetRoomQueueQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StartNextSongAction
{
    public static function execute(Room $room): ?array
    {
        return DB::transaction(function () use ($room) {
            // Mark current playing as completed
            $currentPlaying = KaraokeQueue::where('room_id', $room->id)
                ->where('status', 'playing')
                ->first();

            if ($currentPlaying) {
                $currentPlaying->update([
                    'status' => 'completed',
                    'finished_at' => now(),
                ]);
            }

            // Pop next pending song
            $nextItem = KaraokeQueue::with('song')
                ->where('room_id', $room->id)
                ->where('status', 'pending')
                ->orderBy('position', 'asc')
                ->lockForUpdate()
                ->first();

            $playback = PlaybackSession::firstOrCreate(['room_id' => $room->id]);

            if (!$nextItem) {
                $playback->update([
                    'queue_item_id' => null,
                    'song_id' => null,
                    'state' => 'stopped',
                    'current_time_seconds' => 0,
                    'started_at' => null,
                ]);

                try {
                    broadcast(new SongStartedEvent($room, null));
                } catch (Throwable $e) {
                    Log::warning('Broadcast failed on song stop: ' . $e->getMessage());
                }
                return null;
            }

            $nextItem->update([
                'status' => 'playing',
                'played_at' => now(),
            ]);

            // Re-index remaining pending items
            $remaining = KaraokeQueue::where('room_id', $room->id)
                ->where('status', 'pending')
                ->orderBy('position', 'asc')
                ->get();

            foreach ($remaining as $idx => $rem) {
                $rem->update(['position' => $idx + 1]);
            }

            $playback->update([
                'queue_item_id' => $nextItem->id,
                'song_id' => $nextItem->song_id,
                'state' => 'playing',
                'current_time_seconds' => 0,
                'started_at' => now(),
            ]);

            // Broadcast song start and queue update
            $updatedQueue = GetRoomQueueQuery::execute($room->code);
            try {
                broadcast(new SongStartedEvent($room, $nextItem));
                broadcast(new QueueUpdatedEvent($room, $updatedQueue));
            } catch (Throwable $e) {
                Log::warning('Broadcast failed on song start: ' . $e->getMessage());
            }

            return [
                'id' => $nextItem->id,
                'song_id' => $nextItem->song_id,
                'singer_name' => $nextItem->singer_name,
                'status' => 'playing',
                'song' => $nextItem->song,
            ];
        });
    }
}
