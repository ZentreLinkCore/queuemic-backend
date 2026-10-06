<?php

namespace App\Queries\Song;

use App\Models\Song;
use App\Services\KaraokeProvider\YouTubeKaraokeProvider;
use Illuminate\Support\Collection;

class SearchKaraokeSongsQuery
{
    public static function execute(string $query, int $limit = 20): Collection
    {
        $provider = new YouTubeKaraokeProvider();

        $results = $provider->search($query, $limit);

        // Cache or upsert songs into database
        return $results->map(function ($item) {
            $song = Song::firstOrCreate(
                ['external_video_id' => $item['external_video_id']],
                [
                    'title' => $item['title'],
                    'artist' => $item['artist'],
                    'duration_formatted' => $item['duration_formatted'],
                    'duration_seconds' => $item['duration_seconds'],
                    'thumbnail_url' => $item['thumbnail_url'],
                    'source_provider' => $item['source_provider'] ?? 'youtube',
                ]
            );

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
        });
    }
}
