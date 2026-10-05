<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaybackSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'queue_item_id',
        'song_id',
        'state',
        'current_time_seconds',
        'started_at',
    ];

    protected $casts = [
        'current_time_seconds' => 'integer',
        'started_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function queueItem(): BelongsTo
    {
        return $this->belongsTo(KaraokeQueue::class, 'queue_item_id');
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
