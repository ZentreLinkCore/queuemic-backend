<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Room\CreateRoomAction;
use App\Actions\Room\JoinRoomAction;
use App\Actions\Room\LeaveRoomAction;
use App\Actions\Room\TransferHostAction;
use App\Actions\Room\CloseRoomAction;
use App\Actions\Room\UpdateRoomSettingsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreateRoomRequest;
use App\Http\Requests\V1\JoinRoomRequest;
use App\Http\Requests\V1\TransferHostRequest;
use App\Http\Requests\V1\UpdateRoomSettingsRequest;
use App\Queries\Room\GetRoomDetailsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class RoomController extends Controller
{
    /**
     * Create a new Karaoke room and register the caller as Host.
     */
    public function store(CreateRoomRequest $request): JsonResponse
    {
        try {
            $result = CreateRoomAction::execute($request->validated());
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Room created successfully.',
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'ROOM_CREATION_FAILED',
                'errors' => (object) [],
            ], 400);
        }
    }

    /**
     * Join an existing room as a Guest.
     */
    public function join(JoinRoomRequest $request): JsonResponse
    {
        try {
            $result = JoinRoomAction::execute($request->validated());
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Joined room successfully.',
            ], 200);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'ROOM_NOT_FOUND',
                'errors' => (object) [],
            ], 404);
        } catch (\Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'NICKNAME_TAKEN',
                'errors' => (object) [],
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'ROOM_JOIN_FAILED',
                'errors' => (object) [],
            ], 400);
        }
    }

    /**
     * Get room metadata and current state.
     */
    public function show(string $code): JsonResponse
    {
        try {
            $room = GetRoomDetailsQuery::execute($code);

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found or closed',
                    'code' => 'ROOM_NOT_FOUND',
                    'errors' => (object) [],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $room,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'ROOM_FETCH_FAILED',
                'errors' => (object) [],
            ], 500);
        }
    }

    /**
     * Transfer Host authority to another member in the room.
     */
    public function transferHost(TransferHostRequest $request, string $code): JsonResponse
    {
        try {
            $room = $request->attributes->get('room');
            $currentHost = $request->attributes->get('member');

            $result = TransferHostAction::execute(
                $room,
                $currentHost,
                $request->validated('new_host_member_id')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Host transferred successfully.',
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'MEMBER_NOT_FOUND',
                'errors' => (object) [],
            ], 404);
        } catch (\Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'INVALID_TRANSFER_TARGET',
                'errors' => (object) [],
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'TRANSFER_FAILED',
                'errors' => (object) [],
            ], 400);
        }
    }

    /**
     * Leave the active room.
     */
    public function leave(Request $request, string $code): JsonResponse
    {
        try {
            $room = $request->attributes->get('room');
            $member = $request->attributes->get('member');

            LeaveRoomAction::execute($room, $member);

            return response()->json([
                'success' => true,
                'message' => 'Left room successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'LEAVE_FAILED',
                'errors' => (object) [],
            ], 400);
        }
    }

    /**
     * Update room settings and preferences (Host only).
     */
    public function updateSettings(UpdateRoomSettingsRequest $request, string $code): JsonResponse
    {
        try {
            $room = $request->attributes->get('room');
            $updatedRoom = UpdateRoomSettingsAction::execute($room, $request->validated());

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $updatedRoom->id,
                    'code' => $updatedRoom->code,
                    'name' => $updatedRoom->name,
                    'settings' => $updatedRoom->settings,
                ],
                'message' => 'Room settings updated successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'SETTINGS_UPDATE_FAILED',
                'errors' => (object) [],
            ], 400);
        }
    }

    /**
     * Close the party room (Host only).
     */
    public function destroy(Request $request, string $code): JsonResponse
    {
        try {
            $room = $request->attributes->get('room');
            CloseRoomAction::execute($room);

            return response()->json([
                'success' => true,
                'message' => 'Room closed successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'CLOSE_FAILED',
                'errors' => (object) [],
            ], 400);
        }
    }
}
