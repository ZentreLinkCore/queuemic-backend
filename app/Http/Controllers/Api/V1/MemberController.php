<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Queries\Room\GetRoomMembersQuery;
use Illuminate\Http\JsonResponse;
use Exception;

class MemberController extends Controller
{
    /**
     * List all connected members in a room.
     */
    public function index(string $code): JsonResponse
    {
        try {
            $members = GetRoomMembersQuery::execute($code);

            return response()->json([
                'success' => true,
                'data' => $members,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'MEMBERS_FETCH_FAILED',
                'errors' => (object) [],
            ], 500);
        }
    }
}
