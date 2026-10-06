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
    public static function execute(Room $room, RoomMember $member, array $data): array
    {
        return DB::transaction(function () use ($room, $member, $data) {
            $song = Song::find($data['song_id']);

            if (!$song) {
                throw new NotFoundHttpException('Song not found in catalog.');
            }

            // Calculate next position
            $maxPosition = KaraokeQueue::where('room_id', $room->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->max('position') ?? 0;

            $queueItem = KaraokeQueue::create([
                'room_id' => $room->id,
                'song_id' => $song->id,
                'requested_by_member_id' => $member->id,
                'singer_name' => !empty($data['singer_name']) ? $data['singer_name'] : $member->nickname,
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
        });
    }
}
