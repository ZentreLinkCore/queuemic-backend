<?php

namespace App\Actions\Room;

use App\Models\Room;
use App\Models\RoomMember;
use App\Models\ActivityLog;
use App\Events\MemberJoinedRoomEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class JoinRoomAction
{
    public static function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $roomCode = strtoupper($data['room_code']);
            $room = Room::where('code', $roomCode)->where('status', 'active')->first();

            if (!$room) {
                throw new NotFoundHttpException('Room not found or has been closed.');
            }

            $token = Str::random(40);
            $tokenHash = hash('sha256', $token);

            // Same device coming back (reload, exit, rejoin): reuse its guest seat instead of adding a duplicate.
            // Only guest seats — a host's device joining by code must never take over the host seat.
            $member = !empty($data['device_uuid'])
                ? RoomMember::where('room_id', $room->id)
                    ->where('device_uuid', $data['device_uuid'])
                    ->where('role', 'guest')
                    ->latest('id')
                    ->first()
                : null;

            if ($member) {
                // The old token is unrecoverable (only its hash is stored), so the device gets a fresh one
                $member->update([
                    'session_token_hash' => $tokenHash,
                    'nickname' => $data['nickname'],
                    'is_connected' => true,
                    'last_seen_at' => now(),
                ]);
            } else {
                $colors = ['#EC4899', '#06B6D4', '#10B981', '#F59E0B', '#6366F1', '#3B82F6'];
                $randomColor = $colors[array_rand($colors)];

                $member = RoomMember::create([
                    'room_id' => $room->id,
                    'session_token_hash' => $tokenHash,
                    'device_uuid' => $data['device_uuid'] ?? (string) Str::uuid(),
                    'nickname' => $data['nickname'],
                    'avatar_color' => $randomColor,
                    'role' => 'guest',
                    'is_connected' => true,
                    'last_seen_at' => now(),
                ]);
            }

            ActivityLog::create([
                'room_id' => $room->id,
                'room_member_id' => $member->id,
                'action' => $member->wasRecentlyCreated ? 'member_joined' : 'member_rejoined',
                'payload' => ['nickname' => $member->nickname],
            ]);

            // Broadcast to connected members in the room
            try {
                broadcast(new MemberJoinedRoomEvent($room, $member));
            } catch (Throwable $e) {
                Log::warning('Broadcast failed on member join: ' . $e->getMessage());
            }

            return [
                'room' => [
                    'id' => $room->id,
                    'code' => $room->code,
                    'name' => $room->name,
                    'host_member_id' => $room->host_member_id,
                    'status' => $room->status,
                    'created_at' => $room->created_at->toISOString(),
                ],
                'token' => $token,
                'member' => [
                    'id' => $member->id,
                    'room_id' => $room->id,
                    'nickname' => $member->nickname,
                    'avatar_color' => $member->avatar_color,
                    'role' => $member->role,
                    'is_connected' => true,
                    'last_seen_at' => $member->last_seen_at->toISOString(),
                ],
            ];
        });
    }
}
