<?php

namespace App\Events;

use App\Models\Room;
use App\Models\KaraokeQueue;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SongStartedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Room $room,
        public ?KaraokeQueue $queueItem
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->room->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'song.started';
    }

    public function broadcastWith(): array
    {
        if (!$this->queueItem) {
            return [
                'current_playing' => null,
            ];
        }

        return [
            'current_playing' => [
                'id' => $this->queueItem->id,
                'song_id' => $this->queueItem->song_id,
                'singer_name' => $this->queueItem->singer_name,
                'position' => $this->queueItem->position,
                'status' => 'playing',
                'song' => [
                    'id' => $this->queueItem->song->id,
                    'external_video_id' => $this->queueItem->song->external_video_id,
                    'title' => $this->queueItem->song->title,
                    'artist' => $this->queueItem->song->artist,
                    'duration_formatted' => $this->queueItem->song->duration_formatted,
                    'duration_seconds' => $this->queueItem->song->duration_seconds,
                    'thumbnail_url' => $this->queueItem->song->thumbnail_url,
                ],
            ],
        ];
    }
}
