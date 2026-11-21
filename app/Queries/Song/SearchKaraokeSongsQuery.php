<?php

namespace App\Queries\Song;

use App\Models\Song;
use App\Services\KaraokeProvider\KaraokeProviderInterface;
use App\Services\KaraokeProvider\MockKaraokeProvider;
use App\Services\KaraokeProvider\YouTubeKaraokeProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SearchKaraokeSongsQuery
{
    public static function execute(string $query, int $limit = 20): Collection
    {
        $driver = (string) config('karaoke.driver', 'youtube');
        $normalized = self::normalizeQuery($query, $driver);
        $cacheKey = 'karaoke_search:' . md5($driver . '|' . $normalized . '|' . $limit);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return collect($cached);
        }

        if ($driver === 'youtube' && YouTubeKaraokeProvider::isQuotaExhausted()) {
            return self::searchLocalCatalog($query, $limit);
        }

        $fetch = function () use ($cacheKey, $driver, $query, $limit): Collection {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return collect($cached);
            }

            if ($driver === 'youtube' && YouTubeKaraokeProvider::isQuotaExhausted()) {
                return self::searchLocalCatalog($query, $limit);
            }

            $results = self::searchAndStore($query, $limit, $driver);

            // Never cache an empty page: that is usually a failed or unconfigured provider, not a real "no results"
            if ($results->isNotEmpty()) {
                $ttlMinutes = max(1, (int) config('karaoke.search_cache_minutes', 1440));
                Cache::put($cacheKey, $results->all(), now()->addMinutes($ttlMinutes));

                return $results;
            }

            if ($driver === 'youtube' && YouTubeKaraokeProvider::isQuotaExhausted()) {
                return self::searchLocalCatalog($query, $limit);
            }

            return $results;
        };

        try {
            return Cache::lock("lock:{$cacheKey}", 10)->block(5, $fetch);
        } catch (LockTimeoutException) {
            $cached = Cache::get($cacheKey);
            return is_array($cached) ? collect($cached) : collect();
        }
    }

    private static function normalizeQuery(string $query, string $driver): string
    {
        $normalized = preg_replace('/\s+/', ' ', mb_strtolower(trim($query))) ?? '';
        if ($normalized === '') {
            return 'karaoke hits';
        }

        if ($driver === 'youtube' && !str_contains($normalized, 'karaoke') && !str_contains($normalized, 'instrumental')) {
            $normalized .= ' karaoke';
        }

        return $normalized;
    }

    private static function searchLocalCatalog(string $query, int $limit): Collection
    {
        $trimmed = trim($query);
        $builder = Song::query();

        if ($trimmed !== '') {
            $tokens = array_values(array_filter(
                preg_split('/\s+/', mb_strtolower($trimmed)) ?: [],
                fn (string $word) => $word !== ''
            ));
            $meaningful = array_values(array_filter(
                $tokens,
                fn (string $word) => !in_array($word, ['karaoke', 'instrumental'], true)
            ));
            $terms = !empty($meaningful) ? $meaningful : $tokens;

            $builder->where(function ($outer) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%' . addcslashes($term, '%_\\') . '%';
                    $outer->where(function ($inner) use ($like) {
                        $inner->whereRaw('LOWER(title) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(artist) LIKE ?', [$like]);
                    });
                }
            });
        }

        return $builder
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Song $song) => self::formatSong($song))
            ->values();
    }

    private static function formatSong(Song $song): array
    {
        return [
            'id' => $song->id,
            'external_video_id' => $song->external_video_id,
            'title' => $song->title,
            'artist' => $song->artist,
            'duration_formatted' => $song->duration_formatted,
            'duration_seconds' => $song->duration_seconds,
            'thumbnail_url' => $song->thumbnail_url,
            'source_provider' => $song->source_provider,
        ];
    }

    private static function searchAndStore(string $query, int $limit, string $driver): Collection
    {
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
            ->map(fn (Song $song) => self::formatSong($song))
            ->values();
    }
}
