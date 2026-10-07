<?php

namespace App\Actions\Queue;

use App\Models\Room;
use App\Models\RoomMember;
use App\Models\KaraokeQueue;
use App\Events\QueueUpdatedEvent;
use App\Queries\Queue\GetRoomQueueQuery;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RemoveQueueItemAction
{
    public static function execute(Room $room, RoomMember $member, int $queueItemId): bool
    {
        return DB::transaction(function () use ($room, $member, $queueItemId) {
            $item = KaraokeQueue::where('room_id', $room->id)
                ->where('id', $queueItemId)
                ->where('status', 'pending')
                ->first();

            if (!$item) {
                throw new NotFoundHttpException('Queue item not found.');
            }

            // The host can remove anything; a guest only the songs they queued themselves
            if (!$member->isHost() && (int) $item->requested_by_member_id !== (int) $member->id) {
                throw new AccessDeniedHttpException('Only the room host or the member who queued this song can remove it.');
            }

            $item->update(['status' => 'removed']);

            // Reindex remaining pending items
            $remaining = KaraokeQueue::where('room_id', $room->id)
                ->where('status', 'pending')
                ->orderBy('position', 'asc')
                ->get();

            foreach ($remaining as $idx => $rem) {
                $rem->update(['position' => $idx + 1]);
            }

            $updatedQueue = GetRoomQueueQuery::execute($room->code);
            broadcast(new QueueUpdatedEvent($room, $updatedQueue));

            return true;
        });
    }
}
