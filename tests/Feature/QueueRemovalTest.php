<?php

namespace Tests\Feature;

use App\Models\KaraokeQueue;
use Tests\Feature\Concerns\InteractsWithRooms;
use Tests\TestCase;

class QueueRemovalTest extends TestCase
{
    use InteractsWithRooms;

    public function test_only_the_host_or_the_member_who_queued_a_song_can_remove_it(): void
    {
        $room = $this->createRoom();
        $sam = $this->joinRoom($room['code'], 'Sam');
        $mia = $this->joinRoom($room['code'], 'Mia');
        $this->addSong($room['code'], $room['token'], $this->song('On stage')->id)->assertCreated();

        $samsSong = $this->addSong($room['code'], $sam['token'], $this->song('Zombie')->id)->json('data.id');
        $miasSong = $this->addSong($room['code'], $mia['token'], $this->song('Perfect')->id)->json('data.id');
        $base = "/api/v1/rooms/{$room['code']}/queue";

        // Another guest's song: refused, and nothing changes
        $this->deleteJson("{$base}/{$miasSong}", [], $this->headersFor($sam['token']))
            ->assertForbidden()
            ->assertJsonPath('code', 'HOST_PERMISSION_REQUIRED');
        $this->assertSame('pending', KaraokeQueue::find($miasSong)->status);

        // Own song: allowed
        $this->deleteJson("{$base}/{$samsSong}", [], $this->headersFor($sam['token']))->assertOk();
        $this->assertSame('removed', KaraokeQueue::find($samsSong)->status);

        // Host: anyone's song
        $this->deleteJson("{$base}/{$miasSong}", [], $this->headersFor($room['token']))->assertOk();

        $this->deleteJson("{$base}/999999", [], $this->headersFor($room['token']))
            ->assertNotFound()
            ->assertJsonPath('code', 'QUEUE_ITEM_NOT_FOUND');
    }
}
