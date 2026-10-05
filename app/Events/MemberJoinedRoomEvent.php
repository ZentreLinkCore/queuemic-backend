<?php

namespace App\Events;

use App\Models\Room;
use App\Models\RoomMember;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberJoinedRoomEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Room $room,
        public RoomMember $member
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->room->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'member.joined';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->member->id,
            'nickname' => $this->member->nickname,
            'avatar_color' => $this->member->avatar_color,
            'role' => $this->member->role,
            'is_connected' => true,
        ];
    }
}
