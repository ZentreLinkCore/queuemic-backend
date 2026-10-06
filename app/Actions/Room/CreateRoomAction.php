<?php

namespace App\Actions\Room;

use App\Models\Room;
use App\Models\RoomMember;
use App\Models\PlaybackSession;
use App\Models\ActivityLog;
use App\Services\CodeGenerator\RoomCodeGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateRoomAction
{
    public static function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $code = RoomCodeGeneratorService::generate();
            $token = Str::random(40);
            $tokenHash = hash('sha256', $token);

            $room = Room::create([
                'code' => $code,
                'name' => $data['room_name'] ?? 'QueueMic Lounge',
                'status' => 'active',
                'settings' => [
                    'allow_guest_reorder' => false,
                    'allow_explicit' => true,
                    'allow_guest_playback_control' => false,
                ],
            ]);

            $member = RoomMember::create([
                'room_id' => $room->id,
                'session_token_hash' => $tokenHash,
                'device_uuid' => $data['device_uuid'] ?? (string) Str::uuid(),
                'nickname' => $data['nickname'],
                'avatar_color' => '#8B5CF6',
                'role' => 'host',
                'is_connected' => true,
                'last_seen_at' => now(),
            ]);

            $room->update(['host_member_id' => $member->id]);

            PlaybackSession::create([
                'room_id' => $room->id,
                'state' => 'stopped',
                'current_time_seconds' => 0,
            ]);

            ActivityLog::create([
                'room_id' => $room->id,
                'room_member_id' => $member->id,
                'action' => 'room_created',
                'payload' => ['room_name' => $room->name, 'host' => $member->nickname],
            ]);

            return [
                'room' => [
                    'id' => $room->id,
                    'code' => $room->code,
                    'name' => $room->name,
                    'host_member_id' => $member->id,
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
