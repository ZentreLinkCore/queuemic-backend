<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Broadcasting\BroadcastController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\QueueController;
use App\Http\Controllers\Api\V1\PlaybackController;
use App\Http\Controllers\Api\V1\SongController;
use App\Http\Controllers\Api\V1\MemberController;

use App\Http\Controllers\Api\V1\BroadcastAuthController;

/*
|--------------------------------------------------------------------------
| API Routes (V1)
|--------------------------------------------------------------------------
*/

// Explicit Broadcast Channel Authorization endpoints for API/Mobile clients
Route::get('/broadcasting/config', [BroadcastAuthController::class, 'config']);
Route::post('/broadcasting/auth', [BroadcastAuthController::class, 'authenticate']);

Route::prefix('v1')->group(function () {
    Route::get('/broadcasting/config', [BroadcastAuthController::class, 'config']);
    Route::post('/broadcasting/auth', [BroadcastAuthController::class, 'authenticate']);

    // Public Room Operations
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::post('/rooms/join', [RoomController::class, 'join']);

    // Server clock for playback sync
    Route::get('/time', [PlaybackController::class, 'clock']);

    // Authenticated Room Member Group
    Route::middleware(['room.member'])->group(function () {
        // Catalog Search (room is identified by the room_code query parameter)
        Route::get('/songs/search', [SongController::class, 'search'])->middleware('throttle:song-search');

        // Room Details & Members
        Route::get('/rooms/{code}', [RoomController::class, 'show']);
        Route::post('/rooms/{code}/leave', [RoomController::class, 'leave']);
        Route::get('/rooms/{code}/members', [MemberController::class, 'index']);
        // Presence: the host seat's last_seen_at decides when an abandoned room closes
        Route::post('/rooms/{code}/heartbeat', [MemberController::class, 'heartbeat']);

        // Queue Operations (Guest & Host)
        Route::get('/rooms/{code}/queue', [QueueController::class, 'index']);
        Route::post('/rooms/{code}/queue', [QueueController::class, 'store']);
        // Host or the member who queued the song (checked in RemoveQueueItemAction)
        Route::delete('/rooms/{code}/queue/{queueId}', [QueueController::class, 'destroy']);

        // Playback Status (Read)
        Route::get('/rooms/{code}/playback', [PlaybackController::class, 'show']);

        // Playback Controls (Host or Guests when allowed by room settings)
        Route::middleware(['room.playback_control'])->group(function () {
            Route::post('/rooms/{code}/playback/state', [PlaybackController::class, 'updateState']);
            Route::post('/rooms/{code}/playback/next', [PlaybackController::class, 'next']);
            Route::post('/rooms/{code}/playback/skip', [PlaybackController::class, 'skip']);
        });

        // Queue Reordering (Host or Guests when allowed by room settings)
        Route::middleware(['room.queue_reorder'])->group(function () {
            Route::post('/rooms/{code}/queue/{queueId}/move', [QueueController::class, 'move']);
        });

        // Host Only Operations
        Route::middleware(['room.host'])->group(function () {
            // Room Management & Settings
            Route::patch('/rooms/{code}/settings', [RoomController::class, 'updateSettings']);
            Route::delete('/rooms/{code}', [RoomController::class, 'destroy']);
            Route::post('/rooms/{code}/transfer-host', [RoomController::class, 'transferHost']);
        });
    });
});
