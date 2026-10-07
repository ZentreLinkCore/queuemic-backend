<?php

namespace App\Queries\Song;

use App\Models\Song;
use App\Services\KaraokeProvider\KaraokeProviderInterface;
use App\Services\KaraokeProvider\MockKaraokeProvider;
use App\Services\KaraokeProvider\YouTubeKaraokeProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SearchKaraokeSongsQuery
{
    // YouTube results barely change minute to minute; repeat searches (category chips, reopening search) skip the scrape
    private const CACHE_TTL_MINUTES = 30;

    public static function execute(string $query, int $limit = 20): Collection
    {
        $cacheKey = 'karaoke_search:' . md5(mb_strtolower(trim($query)) . '|' . $limit);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return collect($cached);
        }

        $results = self::searchAndStore($query, $limit);

        // Never cache an empty page: that is usually a failed or unconfigured provider, not a real "no results"
        if ($results->isNotEmpty()) {
            Cache::put($cacheKey, $results->all(), now()->addMinutes(self::CACHE_TTL_MINUTES));
        }

        return $results;
    }

    private static function searchAndStore(string $query, int $limit): Collection
    {
        $driver = config('karaoke.driver', 'youtube');
        $provider = $driver === 'youtube'
            ? new YouTubeKaraokeProvider()
            : new MockKaraokeProvider();

        // Same video can appear twice in a results page; it would also duplicate list keys in the app
        $results = $provider->search($query, $limit)->unique('external_video_id')->values();

        if ($results->isEmpty()) {
            return collect();
        }

        // Cache songs into the database with one upsert + one select for the whole page, instead of a
        // firstOrCreate round trip per song. Existing songs only get updated_at bumped: it records when a
        // search last returned them, so rooms:cleanup never deletes a song a cached search still points to.
        $now = now();
        Song::upsert($results->map(fn ($item) => [
            'external_video_id' => $item['external_video_id'],
            'title' => $item['title'],
            'artist' => $item['artist'],
            'duration_formatted' => $item['duration_formatted'],
            'duration_seconds' => $item['duration_seconds'],
            'thumbnail_url' => $item['thumbnail_url'],
            'source_provider' => $item['source_provider'] ?? 'youtube',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all(), ['external_video_id'], ['updated_at']);

        $songs = Song::whereIn('external_video_id', $results->pluck('external_video_id'))
            ->get()
            ->keyBy('external_video_id');

        // Keep YouTube's ranking order
        return $results
            ->map(fn ($item) => $songs->get($item['external_video_id']))
            ->filter()
            ->map(fn (Song $song) => [
                'id' => $song->id,
                'external_video_id' => $song->external_video_id,
                'title' => $song->title,
                'artist' => $song->artist,
                'duration_formatted' => $song->duration_formatted,
                'duration_seconds' => $song->duration_seconds,
                'thumbnail_url' => $song->thumbnail_url,
                'source_provider' => $song->source_provider,
            ])
            ->values();
    }
}
