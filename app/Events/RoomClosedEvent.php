<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomClosedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Room $room
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->room->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'room.closed';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code' => $this->room->code,
            'message' => 'The party has ended. The host has closed the room.',
        ];
    }
}
