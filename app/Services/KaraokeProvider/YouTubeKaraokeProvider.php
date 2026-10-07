<?php

namespace App\Services\KaraokeProvider;

use Illuminate\Http\Client\Response;
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

        // Official YouTube Data API only (no scraping of youtube.com pages); without a key there is nothing to search
        if (empty($this->apiKey)) {
            Log::warning('YouTube search skipped: YOUTUBE_API_KEY is not set (use KARAOKE_PROVIDER_DRIVER=mock for offline development).');
            return collect();
        }

        return $this->searchViaApiKey($query, $limit);
    }

    protected function searchViaApiKey(string $query, int $limit): Collection
    {
        try {
            $searchTerm = trim($query);
            if (!str_contains(strtolower($searchTerm), 'karaoke') && !str_contains(strtolower($searchTerm), 'instrumental')) {
                $searchTerm .= ' karaoke';
            }

            $response = $this->request('search', [
                'part' => 'snippet',
                'q' => $searchTerm,
                'type' => 'video',
                'videoEmbeddable' => 'true',
                'maxResults' => $limit,
            ]);

            if (!$response->successful()) {
                $this->logRejection('search', $response);
                return collect();
            }

            $items = $response->json('items', []);
            $durations = $this->fetchDurations(array_values(array_filter(
                array_map(fn ($item) => $item['id']['videoId'] ?? null, $items)
            )));

            return collect($items)->map(function ($item) use ($durations) {
                $id = $item['id']['videoId'] ?? '';

                return $this->toResult($id, $item['snippet'] ?? [], $durations[$id] ?? 0);
            });
        } catch (\Exception $e) {
            $this->logException('search', $e);
        }

        return collect();
    }

    // The key travels in a header, never in the URL, so no error message or log line can echo it
    protected function request(string $endpoint, array $query): Response
    {
        return Http::timeout(8)
            ->withHeaders(['X-Goog-Api-Key' => $this->apiKey])
            ->get('https://www.googleapis.com/youtube/v3/' . $endpoint, $query);
    }

    // Google explains a rejection in error.errors.0.reason (quotaExceeded, keyInvalid, badRequest, …)
    protected function logRejection(string $call, Response $response): void
    {
        Log::warning("YouTube Data API {$call} rejected", [
            'status' => $response->status(),
            'reason' => $response->json('error.errors.0.reason'),
            'message' => $this->redactKey((string) $response->json('error.message')),
        ]);
    }

    protected function logException(string $call, \Throwable $e): void
    {
        Log::warning("YouTube Data API {$call} failed: " . $this->redactKey($e->getMessage()));
    }

    // Belt and braces: mask any key= that still appears in a message (e.g. a URL echoed by an HTTP error)
    protected function redactKey(string $message): string
    {
        return preg_replace('/([?&]key=)[^&\s]+/i', '$1***', $message) ?? $message;
    }

    /**
     * Search results carry no length, so one videos call fetches it for the whole page (ISO-8601, e.g. PT4M13S).
     * A length that cannot be read stays 0 — the column's "unknown" default — rather than a made-up value.
     *
     * @param string[] $videoIds
     * @return array<string, int> seconds keyed by video id
     */
    protected function fetchDurations(array $videoIds): array
    {
        if (empty($videoIds)) {
            return [];
        }

        try {
            $response = $this->request('videos', [
                'part' => 'contentDetails',
                'id' => implode(',', $videoIds),
            ]);

            if (!$response->successful()) {
                $this->logRejection('video length lookup', $response);
                return [];
            }

            $durations = [];
            foreach ($response->json('items', []) as $video) {
                $durations[$video['id']] = $this->parseDuration($video['contentDetails']['duration'] ?? null);
            }

            return $durations;
        } catch (\Exception $e) {
            $this->logException('video length lookup', $e);
        }

        return [];
    }

    protected function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, intdiv($seconds % 3600, 60), $seconds % 60)
            : sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    // Seconds in an ISO-8601 length (PT4M13S); 0 — the "unknown" default — when it cannot be read (e.g. a premiere)
    protected function parseDuration(?string $isoDuration): int
    {
        try {
            $interval = new \DateInterval($isoDuration ?: 'PT0S');

            return $interval->d * 86400 + $interval->h * 3600 + $interval->i * 60 + $interval->s;
        } catch (\Exception) {
            return 0;
        }
    }

    protected function toResult(string $videoId, array $snippet, int $seconds): array
    {
        return [
            'external_video_id' => $videoId,
            'title' => html_entity_decode($snippet['title'] ?? 'Karaoke Track', ENT_QUOTES | ENT_HTML5),
            'artist' => html_entity_decode($snippet['channelTitle'] ?? 'Unknown Artist', ENT_QUOTES | ENT_HTML5),
            'duration_formatted' => $this->formatDuration($seconds),
            'duration_seconds' => $seconds,
            'thumbnail_url' => $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['default']['url'] ?? "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
            'source_provider' => 'youtube',
        ];
    }

    /**
     * One video's metadata from the Data API, or null without a key or when the video does not exist.
     */
    public function getDetails(string $externalVideoId): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $response = $this->request('videos', [
                'part' => 'snippet,contentDetails',
                'id' => $externalVideoId,
            ]);

            if (!$response->successful()) {
                $this->logRejection('video details lookup', $response);
                return null;
            }

            // No such video is a normal answer, not a failure
            $video = $response->json('items.0');
            if (!$video) {
                return null;
            }

            return $this->toResult(
                $externalVideoId,
                $video['snippet'] ?? [],
                $this->parseDuration($video['contentDetails']['duration'] ?? null)
            );
        } catch (\Exception $e) {
            $this->logException('video details lookup', $e);
        }

        return null;
    }
}
