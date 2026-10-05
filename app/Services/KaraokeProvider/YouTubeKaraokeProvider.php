<?php

namespace App\Services\KaraokeProvider;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeKaraokeProvider implements KaraokeProviderInterface
{
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('karaoke.youtube_api_key');
    }

    public function search(string $query, int $limit = 20): Collection
    {
        $query = trim($query);
        if (empty($query)) {
            // Return top trending karaoke hits when query is blank
            $query = 'Karaoke Hits';
        }

        // 1. If Google API Key is provided, use official API
        if (!empty($this->apiKey)) {
            $apiResults = $this->searchViaApiKey($query, $limit);
            if ($apiResults->isNotEmpty()) {
                return $apiResults;
            }
        }

        // 2. Direct real-time YouTube scraper (Zero API Key required)
        $publicResults = $this->searchPublic($query, $limit);
        if (!empty($publicResults)) {
            return collect($publicResults);
        }

        // 3. Fallback to curated local catalog if offline or blocked
        return (new MockKaraokeProvider())->search($query, $limit);
    }

    protected function searchViaApiKey(string $query, int $limit): Collection
    {
        try {
            $searchTerm = trim($query);
            if (!str_contains(strtolower($searchTerm), 'karaoke') && !str_contains(strtolower($searchTerm), 'instrumental')) {
                $searchTerm .= ' karaoke';
            }

            $response = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/search', [
                'part' => 'snippet',
                'q' => $searchTerm,
                'type' => 'video',
                'videoEmbeddable' => 'true',
                'maxResults' => $limit,
                'key' => $this->apiKey,
            ]);

            if ($response->successful()) {
                $items = $response->json('items', []);
                return collect($items)->map(function ($item) {
                    $id = $item['id']['videoId'] ?? '';
                    $title = $item['snippet']['title'] ?? 'Karaoke Track';
                    $artist = $item['snippet']['channelTitle'] ?? 'Unknown Artist';
                    $thumbnail = $item['snippet']['thumbnails']['high']['url'] ?? $item['snippet']['thumbnails']['default']['url'] ?? "https://img.youtube.com/vi/{$id}/hqdefault.jpg";

                    return [
                        'external_video_id' => $id,
                        'title' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5),
                        'artist' => html_entity_decode($artist, ENT_QUOTES | ENT_HTML5),
                        'duration_formatted' => '03:45',
                        'duration_seconds' => 225,
                        'thumbnail_url' => $thumbnail,
                        'source_provider' => 'youtube',
                    ];
                });
            }
        } catch (\Exception $e) {
            Log::warning('YouTube API Key search failed, falling back to public search: ' . $e->getMessage());
        }

        return collect();
    }

    protected function searchPublic(string $query, int $limit): array
    {
        try {
            $searchTerm = trim($query);
            if (!str_contains(strtolower($searchTerm), 'karaoke') && !str_contains(strtolower($searchTerm), 'instrumental')) {
                $searchTerm .= ' karaoke';
            }

            $url = 'https://www.youtube.com/results?search_query=' . urlencode($searchTerm);

            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\nAccept-Language: en-US,en;q=0.9\r\n",
                    'timeout' => 8,
                ]
            ];
            $context = stream_context_create($opts);
            $html = @file_get_contents($url, false, $context);

            if (!$html) {
                return [];
            }

            if (preg_match('/ytInitialData\s*=\s*({.+?});/s', $html, $matches)) {
                $data = json_decode($matches[1], true);
                $sections = $data['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'] ?? [];

                $results = [];
                foreach ($sections as $section) {
                    $items = $section['itemSectionRenderer']['contents'] ?? [];
                    foreach ($items as $item) {
                        if (isset($item['videoRenderer'])) {
                            $v = $item['videoRenderer'];
                            $videoId = $v['videoId'] ?? null;
                            if (!$videoId) continue;

                            $title = $v['title']['runs'][0]['text'] ?? 'Karaoke Track';
                            $artist = $v['ownerText']['runs'][0]['text'] ?? 'YouTube';
                            $duration = $v['lengthText']['simpleText'] ?? '03:45';
                            $thumb = $v['thumbnail']['thumbnails'][0]['url'] ?? "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg";

                            // Convert duration string (e.g. 04:15) to seconds
                            $parts = array_map('intval', explode(':', $duration));
                            $seconds = 0;
                            if (count($parts) === 2) {
                                $seconds = $parts[0] * 60 + $parts[1];
                            } elseif (count($parts) === 3) {
                                $seconds = $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
                            } else {
                                $seconds = 210;
                            }

                            $results[] = [
                                'external_video_id' => $videoId,
                                'title' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5),
                                'artist' => html_entity_decode($artist, ENT_QUOTES | ENT_HTML5),
                                'duration_formatted' => $duration,
                                'duration_seconds' => $seconds,
                                'thumbnail_url' => $thumb,
                                'source_provider' => 'youtube',
                            ];

                            if (count($results) >= $limit) {
                                break 2;
                            }
                        }
                    }
                }

                Log::info("🔍 [YouTube Public Search] Query: \"{$searchTerm}\" -> Found " . count($results) . " tracks");
                return $results;
            } else {
                Log::warning("⚠️ [YouTube Public Search] Could not match ytInitialData payload for: \"{$searchTerm}\"");
            }
        } catch (\Exception $e) {
            Log::error('❌ [YouTube Public Search Exception]: ' . $e->getMessage());
        }

        return [];
    }

    public function getDetails(string $externalVideoId): ?array
    {
        return [
            'external_video_id' => $externalVideoId,
            'title' => 'Karaoke Track',
            'artist' => 'YouTube',
            'duration_formatted' => '03:45',
            'duration_seconds' => 225,
            'thumbnail_url' => "https://img.youtube.com/vi/{$externalVideoId}/hqdefault.jpg",
            'source_provider' => 'youtube',
        ];
    }
}
