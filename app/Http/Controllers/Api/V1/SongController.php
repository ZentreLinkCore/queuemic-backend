<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Queries\Song\SearchKaraokeSongsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class SongController extends Controller
{
    /**
     * Search the karaoke catalog.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = (string) $request->input('q', '');
            $limit = (int) $request->input('limit', 20);

            $results = SearchKaraokeSongsQuery::execute($query, $limit);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
