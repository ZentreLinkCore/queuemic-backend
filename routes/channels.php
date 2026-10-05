<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\RoomMember;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('room.{code}', function ($user, string $code) {
    // Check if member token or session user belongs to this active room
    $token = request()->bearerToken() ?? request()->header('X-Session-Token');

    if ($token) {
        $tokenHash = hash('sha256', $token);
        $member = RoomMember::where('session_token_hash', $tokenHash)
            ->whereHas('room', function ($q) use ($code) {
                $q->where('code', strtoupper($code))->where('status', 'active');
            })
            ->first();

        if ($member) {
            return [
                'id' => $member->id,
                'nickname' => $member->nickname,
                'avatar_color' => $member->avatar_color,
                'role' => $member->role,
                'is_connected' => true,
            ];
        }
    }

    return false;
});
