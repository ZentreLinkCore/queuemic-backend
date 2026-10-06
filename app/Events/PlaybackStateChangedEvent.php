<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlaybackStateChangedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Room $room,
        public string $state,
        public float $currentTimeSeconds,
        public ?int $queueItemId = null,
        public ?int $positionAtMs = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->room->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'playback.state_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'state' => $this->state,
            'current_time_seconds' => $this->currentTimeSeconds,
            'queue_item_id' => $this->queueItemId,
            'position_at_ms' => $this->positionAtMs,
        ];
    }
}
