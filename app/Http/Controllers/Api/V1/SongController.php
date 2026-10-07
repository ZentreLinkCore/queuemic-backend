<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SearchSongsRequest;
use App\Queries\Song\SearchKaraokeSongsQuery;
use Illuminate\Http\JsonResponse;
use Exception;

class SongController extends Controller
{
    /**
     * Search the karaoke catalog.
     */
    public function search(SearchSongsRequest $request): JsonResponse
    {
        try {
            $query = (string) ($request->validated('q') ?? '');
            $limit = (int) ($request->validated('limit') ?? 20);

            $results = SearchKaraokeSongsQuery::execute($query, $limit);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'SEARCH_FAILED',
                'errors' => (object) [],
            ], 500);
        }
    }
}
