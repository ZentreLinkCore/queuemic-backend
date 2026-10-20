<?php

/*
|--------------------------------------------------------------------------
| Vercel Entry Point
|--------------------------------------------------------------------------
|
| vercel.json routes every request here (the vercel-php runtime only runs
| files under api/), and this hands it to Laravel's normal front controller.
| Locally Herd / `php artisan serve` keep using public/index.php directly.
|
*/

// The runtime serves this file through PHP's built-in server, so the request looks like it came
// through /api/index.php. Laravel would then treat /api as the app's base URL and strip it from
// every path (/api/v1/rooms routes as v1/rooms and 404s). Present it as public/index.php instead.
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';

// The function's filesystem is read-only except /tmp. Move everything Laravel writes at runtime there:
// storage (the file cache CleanupInactiveRooms uses, compiled views) and the bootstrap cache
// manifests (the runtime installs Composer packages with --no-scripts, so they are built on first request).
$tmp = '/tmp/laravel';

foreach (['storage/framework/cache/data', 'storage/framework/views', 'storage/logs', 'bootstrap'] as $dir) {
    if (!is_dir("{$tmp}/{$dir}")) {
        mkdir("{$tmp}/{$dir}", 0755, true);
    }
}

$_ENV['LARAVEL_STORAGE_PATH'] = "{$tmp}/storage";

foreach (['CONFIG', 'EVENTS', 'PACKAGES', 'ROUTES', 'SERVICES'] as $manifest) {
    $_ENV["APP_{$manifest}_CACHE"] = "{$tmp}/bootstrap/".strtolower($manifest).'.php';
}

require __DIR__.'/../public/index.php';
