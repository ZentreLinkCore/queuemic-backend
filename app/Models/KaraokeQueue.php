<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KaraokeQueue extends Model
{
    use HasFactory;

    protected $table = 'karaoke_queue';

    protected $fillable = [
        'room_id',
        'song_id',
        'requested_by_member_id',
        'singer_name',
        'position',
        'status',
        'played_at',
        'finished_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'played_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(RoomMember::class, 'requested_by_member_id');
    }
}
