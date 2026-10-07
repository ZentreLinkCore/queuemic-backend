<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Room\RecordMemberHeartbeatAction;
use App\Http\Controllers\Controller;
use App\Queries\Room\GetRoomMembersQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * Record that the calling member's app is open (drives host-away room expiry).
     */
    public function heartbeat(Request $request, string $code): JsonResponse
    {
        try {
            $member = RecordMemberHeartbeatAction::execute($request->attributes->get('member'));

            return response()->json([
                'success' => true,
                'data' => [
                    'last_seen_at' => $member->last_seen_at->toISOString(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'HEARTBEAT_FAILED',
                'errors' => (object) [],
            ], 400);
        }
    }
}
