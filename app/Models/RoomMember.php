<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'session_token_hash',
        'device_uuid',
        'nickname',
        'avatar_color',
        'role',
        'is_connected',
        'last_seen_at',
    ];

    protected $casts = [
        'is_connected' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function queueRequests(): HasMany
    {
        return $this->hasMany(KaraokeQueue::class, 'requested_by_member_id');
    }

    public function isHost(): bool
    {
        return $this->role === 'host';
    }
}
