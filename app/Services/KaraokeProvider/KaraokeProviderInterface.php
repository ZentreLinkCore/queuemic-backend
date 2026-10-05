<?php

namespace App\Services\KaraokeProvider;

use Illuminate\Support\Collection;

interface KaraokeProviderInterface
{
    /**
     * Search karaoke tracks matching a search term.
     *
     * @param string $query
     * @param int $limit
     * @return Collection
     */
    public function search(string $query, int $limit = 20): Collection;

    /**
     * Get song details by external video ID.
     *
     * @param string $externalVideoId
     * @return array|null
     */
    public function getDetails(string $externalVideoId): ?array;
}
