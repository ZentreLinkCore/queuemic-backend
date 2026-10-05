<?php

namespace App\Actions\Room;

use App\Models\Room;
use App\Models\RoomMember;
use App\Models\ActivityLog;
use App\Events\HostTransferredEvent;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransferHostAction
{
    public static function execute(Room $room, RoomMember $currentHost, int $newHostMemberId): array
    {
        return DB::transaction(function () use ($room, $currentHost, $newHostMemberId) {
            $newHost = RoomMember::where('id', $newHostMemberId)
                ->where('room_id', $room->id)
                ->first();

            if (!$newHost) {
                throw new NotFoundHttpException('Target member not found in this room.');
            }

            // Demote current host
            $currentHost->update(['role' => 'guest']);

            // Promote new host
            $newHost->update(['role' => 'host']);
            $room->update(['host_member_id' => $newHost->id]);

            ActivityLog::create([
                'room_id' => $room->id,
                'room_member_id' => $currentHost->id,
                'action' => 'host_transferred',
                'payload' => [
                    'from' => $currentHost->nickname,
                    'to' => $newHost->nickname,
                ],
            ]);

            broadcast(new HostTransferredEvent($room, $newHost))->toOthers();

            return [
                'success' => true,
                'new_host_id' => $newHost->id,
                'new_host_nickname' => $newHost->nickname,
            ];
        });
    }
}
