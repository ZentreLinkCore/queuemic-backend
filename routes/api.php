<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\QueueController;
use App\Http\Controllers\Api\V1\PlaybackController;
use App\Http\Controllers\Api\V1\SongController;
use App\Http\Controllers\Api\V1\MemberController;

/*
|--------------------------------------------------------------------------
| API Routes (V1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Public Room Operations
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::post('/rooms/join', [RoomController::class, 'join']);

    // Catalog Search
    Route::get('/songs/search', [SongController::class, 'search']);

    // Authenticated Room Member Group
    Route::middleware(['room.member'])->group(function () {
        // Room Details & Members
        Route::get('/rooms/{code}', [RoomController::class, 'show']);
        Route::post('/rooms/{code}/leave', [RoomController::class, 'leave']);
        Route::get('/rooms/{code}/members', [MemberController::class, 'index']);

        // Queue Operations (Guest & Host)
        Route::get('/rooms/{code}/queue', [QueueController::class, 'index']);
        Route::post('/rooms/{code}/queue', [QueueController::class, 'store']);
        Route::delete('/rooms/{code}/queue/{queueId}', [QueueController::class, 'destroy']);

        // Playback Status (Read)
        Route::get('/rooms/{code}/playback', [PlaybackController::class, 'show']);

        // Host Only Operations
        Route::middleware(['room.host'])->group(function () {
            // Room Management
            Route::delete('/rooms/{code}', [RoomController::class, 'destroy']);
            Route::post('/rooms/{code}/transfer-host', [RoomController::class, 'transferHost']);

            // Queue Reordering
            Route::post('/rooms/{code}/queue/{queueId}/move', [QueueController::class, 'move']);

            // Playback Controls
            Route::post('/rooms/{code}/playback/state', [PlaybackController::class, 'updateState']);
            Route::post('/rooms/{code}/playback/next', [PlaybackController::class, 'next']);
            Route::post('/rooms/{code}/playback/skip', [PlaybackController::class, 'skip']);
        });
    });
});
