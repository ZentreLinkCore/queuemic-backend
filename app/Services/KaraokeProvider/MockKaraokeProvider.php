<?php

namespace App\Services\KaraokeProvider;

use Illuminate\Support\Collection;

/**
 * Offline catalog for demos and tests (KARAOKE_PROVIDER_DRIVER=mock): no network, fixed results.
 * Video ids are placeholders, so these tracks do not play in the YouTube player.
 */
class MockKaraokeProvider implements KaraokeProviderInterface
{
    private const CATALOG = [
        ['mock-bohemian-rhapsody', 'Bohemian Rhapsody (Karaoke Version)', 'Queen', 355],
        ['mock-my-way', 'My Way (Karaoke Version)', 'Frank Sinatra', 275],
        ['mock-zombie', 'Zombie (Karaoke Version)', 'The Cranberries', 306],
        ['mock-perfect', 'Perfect (Karaoke Version)', 'Ed Sheeran', 263],
        ['mock-dont-stop-believin', "Don't Stop Believin' (Karaoke Version)", 'Journey', 251],
        ['mock-dancing-queen', 'Dancing Queen (Karaoke Version)', 'ABBA', 231],
        ['mock-i-will-survive', 'I Will Survive (Karaoke Version)', 'Gloria Gaynor', 198],
        ['mock-livin-on-a-prayer', "Livin' on a Prayer (Karaoke Version)", 'Bon Jovi', 249],
    ];

    public function search(string $query, int $limit = 20): Collection
    {
        $needle = mb_strtolower(trim($query));

        return collect(self::CATALOG)
            ->map(fn (array $track) => $this->toResult(...$track))
            ->filter(fn (array $result) => $needle === ''
                || str_contains(mb_strtolower($result['title']), $needle)
                || str_contains(mb_strtolower($result['artist']), $needle))
            ->take($limit)
            ->values();
    }

    public function getDetails(string $externalVideoId): ?array
    {
        foreach (self::CATALOG as $track) {
            if ($track[0] === $externalVideoId) {
                return $this->toResult(...$track);
            }
        }

        return null;
    }

    private function toResult(string $id, string $title, string $artist, int $seconds): array
    {
        return [
            'external_video_id' => $id,
            'title' => $title,
            'artist' => $artist,
            'duration_formatted' => sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60),
            'duration_seconds' => $seconds,
            'thumbnail_url' => 'https://img.youtube.com/vi/default/hqdefault.jpg',
            'source_provider' => 'custom',
        ];
    }
}
