<?php

namespace App\Actions\Queue;

use App\Models\Room;
use App\Models\KaraokeQueue;
use App\Events\QueueUpdatedEvent;
use App\Queries\Queue\GetRoomQueueQuery;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RemoveQueueItemAction
{
    public static function execute(Room $room, int $queueItemId): bool
    {
        return DB::transaction(function () use ($room, $queueItemId) {
            $item = KaraokeQueue::where('room_id', $room->id)
                ->where('id', $queueItemId)
                ->where('status', 'pending')
                ->first();

            if (!$item) {
                throw new NotFoundHttpException('Queue item not found.');
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
