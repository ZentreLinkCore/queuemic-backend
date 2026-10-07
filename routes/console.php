<?php

use App\Actions\Room\CleanupInactiveRoomsAction;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rooms:cleanup', function () {
    $result = CleanupInactiveRoomsAction::execute();

    $this->info(sprintf(
        'Expired %d idle room(s); deleted %d closed room(s), %d unused song(s), %d expired cache row(s).',
        $result['expired_rooms'],
        $result['deleted_rooms'],
        $result['deleted_songs'],
        $result['deleted_cache_rows'],
    ));
})->purpose('Expire idle karaoke rooms and delete data nothing uses anymore (also runs automatically off API traffic)');
