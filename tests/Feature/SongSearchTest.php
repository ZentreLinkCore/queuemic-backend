<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Concerns\InteractsWithRooms;
use Tests\TestCase;

class SongSearchTest extends TestCase
{
    use InteractsWithRooms;

    public function test_search_is_for_room_members_only(): void
    {
        $room = $this->createRoom();

        $this->getJson('/api/v1/songs/search?q=queen')
            ->assertStatus(400)
            ->assertJsonPath('code', 'ROOM_CODE_REQUIRED');

        $this->getJson("/api/v1/songs/search?q=queen&room_code={$room['code']}")
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->getJson("/api/v1/songs/search?q=queen&room_code={$room['code']}", $this->headersFor($room['token']))
            ->assertOk()
            ->assertJsonCount(2, 'data'); // mock catalog: Queen's own song and "Dancing Queen"
    }

    public function test_search_input_is_validated(): void
    {
        $room = $this->createRoom();
        $search = fn (string $params) => $this->getJson(
            "/api/v1/songs/search?room_code={$room['code']}&{$params}",
            $this->headersFor($room['token'])
        );

        $search('q=b')->assertStatus(422)->assertJsonValidationErrors('q');
        $search('q=' . str_repeat('x', 101))->assertStatus(422)->assertJsonValidationErrors('q');
        $search('q=queen&limit=51')->assertStatus(422)->assertJsonValidationErrors('limit');
        $search('q=')->assertOk(); // blank means trending hits
    }

    public function test_search_is_rate_limited_per_session_not_per_ip(): void
    {
        $room = $this->createRoom();
        $sam = $this->joinRoom($room['code']);
        $url = "/api/v1/songs/search?q=queen&room_code={$room['code']}";

        for ($i = 0; $i < 60; $i++) {
            $this->getJson($url, $this->headersFor($room['token']))->assertOk();
        }

        $this->getJson($url, $this->headersFor($room['token']))
            ->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_REQUESTS');
        $this->getJson($url, $this->headersFor($sam['token']))->assertOk(); // same IP, different session
    }

    public function test_without_an_api_key_search_returns_nothing_and_calls_nobody(): void
    {
        $room = $this->createRoom();
        config(['karaoke.driver' => 'youtube', 'karaoke.youtube_api_key' => null]);
        Http::fake();
        Log::spy();

        $this->getJson("/api/v1/songs/search?q=zombie&room_code={$room['code']}", $this->headersFor($room['token']))
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => []]);

        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'YOUTUBE_API_KEY is not set'));
    }
}
