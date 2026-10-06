<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Playback\StartNextSongAction;
use App\Actions\Playback\UpdatePlaybackStateAction;
use App\Actions\Playback\SkipCurrentSongAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdatePlaybackRequest;
use App\Queries\Queue\GetCurrentPlayingQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PlaybackController extends Controller
{
    /**
     * Get current playing song and progress.
     */
    public function show(string $code): JsonResponse
    {
        try {
            $currentPlaying = GetCurrentPlayingQuery::execute($code);
            return response()->json([
                'success' => true,
                'data' => $currentPlaying,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Server clock, so every device can express "when" in one shared timeline for playback sync.
     */
    public function clock(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['server_time_ms' => (int) floor(microtime(true) * 1000)],
        ]);
    }

    /**
     * Update playback state (playing, paused, buffering) (Host only).
     */
    public function updateState(UpdatePlaybackRequest $request, string $code): JsonResponse
    {
        try {
            $room = $request->attributes->get('room');
            UpdatePlaybackStateAction::execute(
                $room,
                $request->validated('state'),
                (float) $request->validated('current_time_seconds', 0),
                $request->validated('queue_item_id'),
                $request->validated('position_at_ms')
            );

            return response()->json([
                'success' => true,
                'message' => 'Playback state updated.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Advance to the next song in the queue (Host only).
     */
    public function next(Request $request, string $code): JsonResponse
    {
        try {
            $room = $request->attributes->get('room');
            $result = StartNextSongAction::execute($room);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Advanced to next song.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Skip the current song (Host only).
     */
    public function skip(Request $request, string $code): JsonResponse
    {
        try {
            $room = $request->attributes->get('room');
            $member = $request->attributes->get('member');

            $result = SkipCurrentSongAction::execute($room, $member);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Song skipped.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
