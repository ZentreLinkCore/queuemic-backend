<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Song extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_video_id',
        'title',
        'artist',
        'duration_formatted',
        'duration_seconds',
        'thumbnail_url',
        'source_provider',
        'metadata_payload',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'metadata_payload' => 'array',
    ];

    public function queueItems(): HasMany
    {
        return $this->hasMany(KaraokeQueue::class);
    }
}
