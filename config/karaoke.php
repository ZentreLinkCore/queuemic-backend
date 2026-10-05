<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Karaoke Provider Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: 'mock', 'youtube'
    |
    */
    'driver' => env('KARAOKE_PROVIDER_DRIVER', 'youtube'),

    'youtube_api_key' => env('YOUTUBE_API_KEY', null),
];
