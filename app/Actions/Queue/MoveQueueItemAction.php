<?php

namespace App\Actions\Queue;

use App\Models\Room;
use App\Models\KaraokeQueue;
use App\Events\QueueUpdatedEvent;
use App\Queries\Queue\GetRoomQueueQuery;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class MoveQueueItemAction
{
    public static function execute(Room $room, int $queueItemId, string $direction): array
    {
        return DB::transaction(function () use ($room, $queueItemId, $direction) {
            $items = KaraokeQueue::where('room_id', $room->id)
                ->where('status', 'pending')
                ->orderBy('position', 'asc')
                ->lockForUpdate()
                ->get();

            $currentIndex = $items->search(fn($q) => $q->id === $queueItemId);

            if ($currentIndex === false) {
                throw new NotFoundHttpException('Queue item not found or already played.');
            }

            $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;

            if ($targetIndex < 0 || $targetIndex >= $items->count()) {
                throw new UnprocessableEntityHttpException('Cannot move item beyond queue bounds.');
            }

            $currentItem = $items[$currentIndex];
            $targetItem = $items[$targetIndex];

            // Swap positions
            $tempPos = $currentItem->position;
            $currentItem->update(['position' => $targetItem->position]);
            $targetItem->update(['position' => $tempPos]);

            $updatedQueue = GetRoomQueueQuery::execute($room->code);
            broadcast(new QueueUpdatedEvent($room, $updatedQueue));

            return [
                'success' => true,
                'queue' => $updatedQueue,
            ];
        });
    }
}
