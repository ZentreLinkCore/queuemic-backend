<?php

namespace App\Actions\Room;

use App\Models\Room;
use App\Models\ActivityLog;
use App\Events\RoomClosedEvent;
use Illuminate\Support\Facades\DB;

class CloseRoomAction
{
    public static function execute(Room $room): bool
    {
        return DB::transaction(function () use ($room) {
            $room->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            ActivityLog::create([
                'room_id' => $room->id,
                'action' => 'room_closed',
                'payload' => ['closed_at' => now()->toISOString()],
            ]);

            broadcast(new RoomClosedEvent($room));

            return true;
        });
    }
}
