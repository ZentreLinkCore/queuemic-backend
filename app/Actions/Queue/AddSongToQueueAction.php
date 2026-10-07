<?php

namespace App\Actions\Queue;

use App\Actions\Playback\StartNextSongAction;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\Song;
use App\Models\KaraokeQueue;
use App\Models\ActivityLog;
use App\Events\QueueUpdatedEvent;
use App\Queries\Queue\GetRoomQueueQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class AddSongToQueueAction
{
    // A double tap or a retried request re-sends the same song for the same singer within moments
    private const DUPLICATE_WINDOW_SECONDS = 5;

    public static function execute(Room $room, RoomMember $member, array $data): array
    {
        return DB::transaction(function () use ($room, $member, $data) {
            $song = Song::find($data['song_id']);

            if (!$song) {
                throw new NotFoundHttpException('Song not found in catalog.');
            }

            // Calculate next position (avoid FOR UPDATE with aggregate functions on Postgres)
            $lastItem = KaraokeQueue::where('room_id', $room->id)
                ->where('status', 'pending')
                ->orderBy('position', 'desc')
                ->lockForUpdate()
                ->first();

            $maxPosition = $lastItem?->position ?? 0;

            $singerName = !empty($data['singer_name']) ? $data['singer_name'] : $member->nickname;

            // Same song, same singer, seconds apart: hand back the item already queued instead of a duplicate
            $duplicate = KaraokeQueue::where('room_id', $room->id)
                ->where('song_id', $song->id)
                ->where('requested_by_member_id', $member->id)
                ->where('singer_name', $singerName)
                ->whereIn('status', ['pending', 'playing'])
                ->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_WINDOW_SECONDS))
                ->first();

            if ($duplicate) {
                return self::toPayload($duplicate, $song);
            }

            $queueItem = KaraokeQueue::create([
                'room_id' => $room->id,
                'song_id' => $song->id,
                'requested_by_member_id' => $member->id,
                'singer_name' => $singerName,
                'position' => $maxPosition + 1,
                'status' => 'pending',
            ]);

            ActivityLog::create([
                'room_id' => $room->id,
                'room_member_id' => $member->id,
                'action' => 'song_queued',
                'payload' => [
                    'song_title' => $song->title,
                    'singer' => $queueItem->singer_name,
                    'position' => $queueItem->position,
                ],
            ]);

            $isStageEmpty = !KaraokeQueue::where('room_id', $room->id)
                ->where('status', 'playing')
                ->exists();

            if ($isStageEmpty) {
                // Start it on the server so every device shares one "now playing" and start time
                // (broadcasts song.started + queue.updated)
                StartNextSongAction::execute($room);
                $queueItem->refresh();
            } else {
                // Broadcast new queue state
                $updatedQueue = GetRoomQueueQuery::execute($room->code);
                try {
                    broadcast(new QueueUpdatedEvent($room, $updatedQueue));
                } catch (Throwable $e) {
                    Log::warning('Broadcast failed on add queue: ' . $e->getMessage());
                }
            }

            return self::toPayload($queueItem, $song);
        });
    }

    private static function toPayload(KaraokeQueue $queueItem, Song $song): array
    {
        return [
            'id' => $queueItem->id,
            'room_id' => $queueItem->room_id,
            'song_id' => $queueItem->song_id,
            'requested_by_member_id' => $queueItem->requested_by_member_id,
            'singer_name' => $queueItem->singer_name,
            'position' => $queueItem->position,
            'status' => $queueItem->status,
            'song' => $song,
        ];
    }
}
