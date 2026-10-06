<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use App\Models\RoomMember;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Broadcast::resolveAuthenticatedUserUsing(function ($request) {
            $token = $request->bearerToken() 
                ?? $request->header('X-Session-Token') 
                ?? $request->input('token')
                ?? $request->header('Authorization');

            if ($token && str_starts_with($token, 'Bearer ')) {
                $token = substr($token, 7);
            }

            if ($token) {
                $tokenHash = hash('sha256', trim($token));
                return RoomMember::where('session_token_hash', $tokenHash)->first();
            }

            return null;
        });
    }
}
