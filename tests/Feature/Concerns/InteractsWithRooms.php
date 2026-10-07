<?php

namespace Tests\Feature\Concerns;

use App\Http\Middleware\CleanupInactiveRooms;
use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Room fixtures over the real API. Every test runs offline: stray HTTP calls fail the test, search uses the
 * mock provider, and the after-response cleanup (which writes to the real file cache) is switched off.
 */
trait InteractsWithRooms
{
    use RefreshDatabase;

    protected function setUpInteractsWithRooms(): void
    {
        Http::preventStrayRequests();
        config(['karaoke.driver' => 'mock']);
        $this->withoutMiddleware(CleanupInactiveRooms::class);
    }

    /**
     * @return array{code: string, token: string, member_id: int, room_id: int}
     */
    protected function createRoom(string $nickname = 'Alex'): array
    {
        $data = $this->postJson('/api/v1/rooms', [
            'room_name' => 'Friday Jam',
            'nickname' => $nickname,
            'device_uuid' => 'dev-' . Str::lower($nickname),
        ])->assertCreated()->json('data');

        return [
            'code' => $data['room']['code'],
            'token' => $data['token'],
            'member_id' => $data['member']['id'],
            'room_id' => $data['room']['id'],
        ];
    }

    /**
     * @return array{token: string, member_id: int}
     */
    protected function joinRoom(string $code, string $nickname = 'Sam'): array
    {
        $data = $this->postJson('/api/v1/rooms/join', [
            'room_code' => $code,
            'nickname' => $nickname,
            'device_uuid' => 'dev-' . Str::lower($nickname),
        ])->assertOk()->json('data');

        return ['token' => $data['token'], 'member_id' => $data['member']['id']];
    }

    protected function song(string $title = 'Zombie'): Song
    {
        return Song::create([
            'external_video_id' => 'vid-' . Str::random(8),
            'title' => $title,
            'artist' => 'Artist',
            'thumbnail_url' => 'https://img.youtube.com/vi/x/hqdefault.jpg',
        ]);
    }

    protected function headersFor(string $token, array $extra = []): array
    {
        return array_merge(['Authorization' => 'Bearer ' . $token], $extra);
    }

    protected function addSong(string $code, string $token, int $songId, array $extraHeaders = [], array $body = [])
    {
        return $this->postJson(
            "/api/v1/rooms/{$code}/queue",
            array_merge(['song_id' => $songId], $body),
            $this->headersFor($token, $extraHeaders)
        );
    }
}
