<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Karaoke Provider Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: 'youtube', 'mock' (offline fixtures for demos and tests)
    |
    */
    'driver' => env('KARAOKE_PROVIDER_DRIVER', 'youtube'),

    'youtube_api_key' => env('YOUTUBE_API_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | Cleanup (runs automatically at most every 5 minutes off API traffic; manual: php artisan rooms:cleanup)
    |--------------------------------------------------------------------------
    |
    | host_away_minutes: a room whose host has not been seen for this long (no heartbeat, no request)
    | is closed, then deleted together with its members, queue, playback and logs.
    | unused_song_days: cached YouTube songs no queue uses and no search has returned for this long.
    |
    */
    'host_away_minutes' => env('KARAOKE_HOST_AWAY_MINUTES', 30),

    'unused_song_days' => env('KARAOKE_UNUSED_SONG_DAYS', 7),
];
