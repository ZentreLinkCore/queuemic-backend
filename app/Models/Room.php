<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'host_member_id',
        'status',
        'settings',
        'closed_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'closed_at' => 'datetime',
    ];

    public function host(): BelongsTo
    {
        return $this->belongsTo(RoomMember::class, 'host_member_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(RoomMember::class);
    }

    public function queueItems(): HasMany
    {
        return $this->hasMany(KaraokeQueue::class);
    }

    public function playbackSession(): HasOne
    {
        return $this->hasOne(PlaybackSession::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
