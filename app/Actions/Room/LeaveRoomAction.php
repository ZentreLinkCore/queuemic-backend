<?php

namespace App\Actions\Room;

use App\Models\Room;
use App\Models\RoomMember;
use App\Models\ActivityLog;
use App\Events\MemberLeftRoomEvent;
use Illuminate\Support\Facades\DB;

class LeaveRoomAction
{
    public static function execute(Room $room, RoomMember $member): bool
    {
        return DB::transaction(function () use ($room, $member) {
            $member->update(['is_connected' => false]);

            ActivityLog::create([
                'room_id' => $room->id,
                'room_member_id' => $member->id,
                'action' => 'member_left',
                'payload' => ['nickname' => $member->nickname],
            ]);

            broadcast(new MemberLeftRoomEvent($room, $member->id))->toOthers();

            return true;
        });
    }
}
