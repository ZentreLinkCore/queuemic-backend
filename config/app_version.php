<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile App Version Configuration
    |--------------------------------------------------------------------------
    |
    | Defines the latest available release version and the minimum required
    | version for mobile clients. Clients below the minimum version are
    | rejected with HTTP 426 Upgrade Required.
    |
    */
    'latest_version' => env('APP_LATEST_VERSION', '1.0.0'),

    'minimum_supported_version' => env('APP_MIN_SUPPORTED_VERSION', '1.0.0'),

    // "?:" and not the env() default, so a variable that is set but empty also falls back (spec EC-02)
    'download_url_android' => env('APP_UPDATE_URL_ANDROID') ?: 'https://play.google.com/store/apps/details?id=com.queuemic.app',

    'download_url_ios' => env('APP_UPDATE_URL_IOS') ?: 'https://apps.apple.com/app/queuemic/id123456789',

    'release_notes' => env('APP_RELEASE_NOTES') ?: 'Performance improvements and bug fixes.',

    'is_ota_compatible' => (bool) env('APP_IS_OTA_COMPATIBLE', false),

    // ISO 8601 date the latest release went live; null when unset
    'published_at' => env('APP_RELEASE_PUBLISHED_AT'),
];
