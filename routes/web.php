<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\BroadcastAuthController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/broadcasting/auth', [BroadcastAuthController::class, 'authenticate']);

Route::get('/test-broadcast', function () {
    event(new \App\Events\TestMessage('Hello QueueMic'));

    return 'Broadcast sent!';
});
