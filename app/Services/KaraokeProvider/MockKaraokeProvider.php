<?php

namespace App\Services\KaraokeProvider;

use Illuminate\Support\Collection;

class MockKaraokeProvider implements KaraokeProviderInterface
{
    protected array $catalog = [
        [
            'external_video_id' => 'qFkNATtc3mc',
            'title' => 'My Way (Karaoke Version)',
            'artist' => 'Frank Sinatra',
            'duration_formatted' => '04:35',
            'duration_seconds' => 275,
            'thumbnail_url' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=400&q=80',
            'source_provider' => 'youtube',
        ],
        [
            'external_video_id' => '2Vv-BfVoq4g',
            'title' => 'Perfect (Acoustic Karaoke)',
            'artist' => 'Ed Sheeran',
            'duration_formatted' => '04:23',
            'duration_seconds' => 263,
            'thumbnail_url' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=400&q=80',
            'source_provider' => 'youtube',
        ],
        [
            'external_video_id' => '6Ejga4kJUts',
            'title' => 'Zombie (Rock Karaoke)',
            'artist' => 'The Cranberries',
            'duration_formatted' => '05:06',
            'duration_seconds' => 306,
            'thumbnail_url' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400&q=80',
            'source_provider' => 'youtube',
        ],
        [
            'external_video_id' => 'fJ9rUzIMcZQ',
            'title' => 'Bohemian Rhapsody',
            'artist' => 'Queen',
            'duration_formatted' => '05:55',
            'duration_seconds' => 355,
            'thumbnail_url' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=400&q=80',
            'source_provider' => 'youtube',
        ],
        [
            'external_video_id' => 'hT_nvWreIhg',
            'title' => 'Counting Stars',
            'artist' => 'OneRepublic',
            'duration_formatted' => '04:17',
            'duration_seconds' => 257,
            'thumbnail_url' => 'https://images.unsplash.com/photo-1487180144351-b8472da7d491?w=400&q=80',
            'source_provider' => 'youtube',
        ],
        [
            'external_video_id' => '09R8_2nJtjg',
            'title' => 'Sugar',
            'artist' => 'Maroon 5',
            'duration_formatted' => '03:55',
            'duration_seconds' => 235,
            'thumbnail_url' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=400&q=80',
            'source_provider' => 'youtube',
        ],
        [
            'external_video_id' => 'k4V3Mo61fJM',
            'title' => 'Fix You (Piano Karaoke)',
            'artist' => 'Coldplay',
            'duration_formatted' => '04:55',
            'duration_seconds' => 295,
            'thumbnail_url' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=400&q=80',
            'source_provider' => 'youtube',
        ],
        [
            'external_video_id' => 'YQHsXMglC9A',
            'title' => 'Hello',
            'artist' => 'Adele',
            'duration_formatted' => '04:55',
            'duration_seconds' => 295,
            'thumbnail_url' => 'https://images.unsplash.com/photo-1447752875215-b2761acb3c5d?w=400&q=80',
            'source_provider' => 'youtube',
        ],
    ];

    public function search(string $query, int $limit = 20): Collection
    {
        if (empty(trim($query))) {
            return collect($this->catalog)->take($limit);
        }

        $q = strtolower(trim($query));

        return collect($this->catalog)->filter(function ($item) use ($q) {
            return str_contains(strtolower($item['title']), $q) || str_contains(strtolower($item['artist']), $q);
        })->take($limit)->values();
    }

    public function getDetails(string $externalVideoId): ?array
    {
        return collect($this->catalog)->firstWhere('external_video_id', $externalVideoId);
    }
}
