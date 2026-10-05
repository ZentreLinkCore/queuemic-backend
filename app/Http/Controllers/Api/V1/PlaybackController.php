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
     * Update playback state (playing, paused, buffering) (Host only).
     */
    public function updateState(UpdatePlaybackRequest $request, string $code): JsonResponse
    {
        try {
            $room = $request->attributes->get('room');
            UpdatePlaybackStateAction::execute(
                $room,
                $request->validated('state'),
                $request->validated('current_time_seconds', 0)
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
