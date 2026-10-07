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
    public static function execute(Room $room, int $queueItemId, ?string $direction = null, ?int $targetPosition = null): array
    {
        return DB::transaction(function () use ($room, $queueItemId, $direction, $targetPosition) {
            $items = KaraokeQueue::where('room_id', $room->id)
                ->where('status', 'pending')
                ->orderBy('position', 'asc')
                ->lockForUpdate()
                ->get();

            $currentIndex = $items->search(fn($q) => $q->id === $queueItemId);

            if ($currentIndex === false) {
                throw new NotFoundHttpException('Queue item not found or already played.');
            }

            $count = $items->count();
            if ($count <= 1) {
                $updatedQueue = GetRoomQueueQuery::execute($room->code);
                return [
                    'success' => true,
                    'queue' => $updatedQueue,
                ];
            }

            $targetIndex = $currentIndex;
            if ($targetPosition !== null) {
                $targetIndex = max(0, min($count - 1, $targetPosition - 1));
            } elseif ($direction === 'up') {
                $targetIndex = max(0, $currentIndex - 1);
            } elseif ($direction === 'down') {
                $targetIndex = min($count - 1, $currentIndex + 1);
            } elseif ($direction === 'top') {
                $targetIndex = 0;
            } elseif ($direction === 'bottom') {
                $targetIndex = $count - 1;
            }

            if ($targetIndex !== $currentIndex) {
                // Remove item from current position and insert at target position
                $itemToMove = $items->splice($currentIndex, 1)->first();
                $items->splice($targetIndex, 0, [$itemToMove]);

                // Renumber all positions 1..N
                foreach ($items as $idx => $item) {
                    $newPos = $idx + 1;
                    if ($item->position !== $newPos) {
                        $item->update(['position' => $newPos]);
                    }
                }
            }

            $updatedQueue = GetRoomQueueQuery::execute($room->code);
            broadcast(new QueueUpdatedEvent($room, $updatedQueue));

            return [
                'success' => true,
                'queue' => $updatedQueue,
            ];
        });
    }
}
