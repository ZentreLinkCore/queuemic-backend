<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Karaoke Provider Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: 'youtube'
    |
    */
    'driver' => env('KARAOKE_PROVIDER_DRIVER', 'youtube'),

    'youtube_api_key' => env('YOUTUBE_API_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | Cleanup (runs automatically at most every 5 minutes off API traffic; manual: php artisan rooms:cleanup)
    |--------------------------------------------------------------------------
    |
    | room_idle_minutes: a room with no karaoke activity for this long (no song added, nothing playing)
    | is closed, then deleted together with its members, queue, playback and logs.
    | unused_song_days: cached YouTube songs no queue uses and no search has returned for this long.
    |
    */
    'room_idle_minutes' => env('KARAOKE_ROOM_IDLE_MINUTES', 30),

    'unused_song_days' => env('KARAOKE_UNUSED_SONG_DAYS', 7),
];
