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

class HostTransferredEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Room $room,
        public RoomMember $newHost
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->room->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'host.transferred';
    }

    public function broadcastWith(): array
    {
        return [
            'new_host_id' => $this->newHost->id,
            'new_host_nickname' => $this->newHost->nickname,
        ];
    }
}
