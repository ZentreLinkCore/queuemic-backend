<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Broadcast;
use Tests\Feature\Concerns\InteractsWithRooms;
use Tests\TestCase;

class ChannelAuthTest extends TestCase
{
    use InteractsWithRooms;

    public function test_a_member_can_join_their_active_rooms_channel(): void
    {
        $room = $this->createRoom();

        $this->authorizeChannel($room['token'], $room['code'])
            ->assertOk()
            ->assertJsonStructure(['auth', 'channel_data']);
    }

    public function test_members_cannot_join_another_rooms_channel(): void
    {
        $alex = $this->createRoom('Alex');
        $bea = $this->createRoom('Bea');

        $this->authorizeChannel($alex['token'], $bea['code'])
            ->assertForbidden()
            ->assertJsonPath('code', 'BROADCAST_AUTH_FAILED');
    }

    public function test_a_closed_rooms_members_cannot_reopen_its_channel(): void
    {
        $room = $this->createRoom();
        $sam = $this->joinRoom($room['code']);
        $this->deleteJson("/api/v1/rooms/{$room['code']}", [], $this->headersFor($room['token']))->assertOk();

        $this->authorizeChannel($sam['token'], $room['code'])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'BROADCAST_AUTH_FAILED');
    }

    private function authorizeChannel(string $token, string $code)
    {
        // The test env broadcasts to "null", which authorizes nothing. Switch to a real Pusher-protocol broadcaster
        // with throwaway credentials only now, after the fixtures: its events would otherwise go out over the
        // network. Auth signing is local. The channel callbacks are registered on the broadcaster in use.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');

        return $this->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "presence-room.{$code}",
        ], $this->headersFor($token));
    }
}
