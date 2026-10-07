<?php

namespace App\Actions\Room;

use App\Models\RoomMember;

class RecordMemberHeartbeatAction
{
    /**
     * Mark the member as present now. The host seat's last_seen_at decides when an abandoned room closes
     * (ExpireRoomIfIdleAction), so the host app reports in even when nothing is playing.
     */
    public static function execute(RoomMember $member): RoomMember
    {
        $member->update([
            'last_seen_at' => now(),
            'is_connected' => true,
        ]);

        return $member;
    }
}
