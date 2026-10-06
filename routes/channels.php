<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\RoomMember;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('room.{code}', function ($user = null, ?string $code = null) {
    if (!$user) {
        $token = request()->bearerToken() 
            ?? request()->header('X-Session-Token') 
            ?? request()->input('token')
            ?? request()->header('Authorization');

        if ($token && str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        if ($token) {
            $tokenHash = hash('sha256', trim($token));
            $user = RoomMember::with('room')->where('session_token_hash', $tokenHash)->first();
        }
    }

    if ($user) {
        if ($code && $user->room && strtoupper($user->room->code) !== strtoupper($code)) {
            return false;
        }

        return [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar_color' => $user->avatar_color,
            'role' => $user->role,
            'is_connected' => true,
        ];
    }

    return false;
});
