<?php

namespace App\Actions\Room;

use App\Models\Room;
use App\Models\Song;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanupInactiveRoomsAction
{
    /**
     * Close rooms nobody has sung in for a while, then delete closed rooms and data nothing reads anymore.
     *
     * @return array{expired_rooms: int, deleted_rooms: int, deleted_songs: int, deleted_cache_rows: int}
     */
    public static function execute(): array
    {
        // Same host-away rule the entry points use (join, every room request), so nothing disagrees about it
        $idleCutoff = now()->subMinutes((int) config('karaoke.host_away_minutes', 30));
        $expiredRooms = Room::where('status', 'active')
            ->where('created_at', '<', $idleCutoff)
            ->get()
            ->filter(fn (Room $room) => ExpireRoomIfIdleAction::execute($room))
            ->count();

        // Closed rooms (ended by the host or expired above) are never read again. The foreign keys cascade,
        // so their members, queue, playback session and activity logs go with them.
        $deletedRooms = Room::where('status', 'closed')->delete();

        // Songs are a cache of search results: drop the ones no queue uses and no search has returned lately
        $deletedSongs = Song::where('updated_at', '<', now()->subDays((int) config('karaoke.unused_song_days', 7)))
            ->whereDoesntHave('queueItems')
            ->delete();

        // The database cache store never removes expired entries on its own
        $deletedCacheRows = 0;
        $nowTimestamp = now()->getTimestamp();
        foreach ([config('cache.stores.database.table') ?: 'cache', config('cache.stores.database.lock_table') ?: 'cache_locks'] as $table) {
            if (Schema::hasTable($table)) {
                $deletedCacheRows += DB::table($table)->where('expiration', '<=', $nowTimestamp)->delete();
            }
        }

        return [
            'expired_rooms' => $expiredRooms,
            'deleted_rooms' => $deletedRooms,
            'deleted_songs' => $deletedSongs,
            'deleted_cache_rows' => $deletedCacheRows,
        ];
    }
}
