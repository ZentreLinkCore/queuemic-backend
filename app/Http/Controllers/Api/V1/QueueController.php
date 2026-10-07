<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Queue\AddSongToQueueAction;
use App\Actions\Queue\MoveQueueItemAction;
use App\Actions\Queue\RemoveQueueItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AddQueueRequest;
use App\Http\Requests\V1\MoveQueueRequest;
use App\Queries\Queue\GetRoomQueueQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class QueueController extends Controller
{
    /**
     * Get the active room queue.
     */
    public function index(string $code): JsonResponse
    {
        try {
            $queue = GetRoomQueueQuery::execute($code);
            return response()->json([
                'success' => true,
                'data' => $queue,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a song to the queue.
     */
    public function store(AddQueueRequest $request, string $code): JsonResponse
    {
        try {
            $room = $request->attributes->get('room');
            $member = $request->attributes->get('member');

            $result = AddSongToQueueAction::execute($room, $member, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Song added to queue.',
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'QUEUE_ADD_FAILED',
            ], 400);
        }
    }

    /**
     * Move an item in the queue (Host or Guests when allowed).
     */
    public function move(MoveQueueRequest $request, string $code, int $queueId): JsonResponse
    {
        try {
            $room = $request->attributes->get('room');
            $result = MoveQueueItemAction::execute(
                $room,
                $queueId,
                $request->validated('direction'),
                $request->validated('target_position')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Queue reordered.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'MOVE_FAILED',
            ], 400);
        }
    }

    /**
     * Remove an item from the queue (Host or Item Requester).
     */
    public function destroy(Request $request, string $code, int $queueId): JsonResponse
    {
        try {
            $room = $request->attributes->get('room');
            RemoveQueueItemAction::execute($room, $queueId);

            return response()->json([
                'success' => true,
                'message' => 'Queue item removed.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'REMOVE_FAILED',
            ], 400);
        }
    }
}
