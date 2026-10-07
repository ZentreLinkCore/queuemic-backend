<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'broadcasting/auth',
            'api/*',
        ]);

        // Expires idle rooms and deletes unused data piggybacking on API traffic (no scheduler process needed)
        $middleware->api(append: [
            \App\Http\Middleware\CleanupInactiveRooms::class,
        ]);

        $middleware->alias([
            'room.member' => \App\Http\Middleware\EnsureRoomMember::class,
            'room.host' => \App\Http\Middleware\EnsureRoomHost::class,
            'room.playback_control' => \App\Http\Middleware\EnsurePlaybackControl::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
