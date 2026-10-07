<?php

namespace Tests\Feature;

use App\Actions\Room\CleanupInactiveRoomsAction;
use App\Models\Room;
use App\Models\RoomMember;
use Tests\Feature\Concerns\InteractsWithRooms;
use Tests\TestCase;

class RoomExpiryTest extends TestCase
{
    use InteractsWithRooms;

    private const AWAY_MESSAGE = 'This party ended because the host has been away for 30 minutes.';

    public function test_heartbeat_records_the_callers_presence(): void
    {
        $room = $this->createRoom();
        $this->travel(5)->minutes();

        $this->postJson("/api/v1/rooms/{$room['code']}/heartbeat", [], $this->headersFor($room['token']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['last_seen_at']]);

        $this->assertTrue(RoomMember::find($room['member_id'])->last_seen_at->gte(now()->subSecond()));
    }

    public function test_a_room_stays_open_while_its_host_is_around_even_with_no_songs(): void
    {
        $room = $this->createRoom();
        $sam = $this->joinRoom($room['code']);

        $this->travel(20)->minutes();
        $this->postJson("/api/v1/rooms/{$room['code']}/heartbeat", [], $this->headersFor($room['token']))->assertOk();
        $this->travel(20)->minutes(); // room is 40 minutes old, nothing ever queued, host seen 20 minutes ago

        $this->getJson("/api/v1/rooms/{$room['code']}/queue", $this->headersFor($sam['token']))->assertOk();
        $this->assertSame('active', Room::find($room['room_id'])->status);
    }

    public function test_a_room_closes_once_the_host_has_been_away_30_minutes_even_if_guests_are_busy(): void
    {
        $room = $this->createRoom();
        $sam = $this->joinRoom($room['code']);

        $this->travel(20)->minutes();
        $this->addSong($room['code'], $sam['token'], $this->song()->id)->assertCreated(); // guest activity
        $this->travel(11)->minutes(); // host last seen 31 minutes ago

        $this->getJson("/api/v1/rooms/{$room['code']}/queue", $this->headersFor($sam['token']))
            ->assertNotFound()
            ->assertJsonPath('code', 'ROOM_NOT_FOUND')
            ->assertJsonPath('message', self::AWAY_MESSAGE);

        $this->assertSame('closed', Room::find($room['room_id'])->status);
    }

    public function test_a_returning_host_keeps_the_room_within_30_minutes(): void
    {
        $room = $this->createRoom();
        $this->travel(29)->minutes();

        $this->getJson("/api/v1/rooms/{$room['code']}", $this->headersFor($room['token']))
            ->assertOk()
            ->assertJsonPath('data.host_member_id', $room['member_id']);
    }

    public function test_cleanup_closes_and_deletes_rooms_whose_host_left(): void
    {
        $abandoned = $this->createRoom('Alex');
        $this->travel(31)->minutes();
        $fresh = $this->createRoom('Bea');

        $result = CleanupInactiveRoomsAction::execute();

        $this->assertSame(1, $result['expired_rooms']);
        $this->assertNull(Room::find($abandoned['room_id']));
        $this->assertNotNull(Room::find($fresh['room_id']));
    }
}
