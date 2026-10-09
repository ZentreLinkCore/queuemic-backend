<?php

namespace Tests\Feature;

use App\Events\PlaybackStateChangedEvent;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Concerns\InteractsWithRooms;
use Tests\TestCase;

class PlaybackBroadcastThrottleTest extends TestCase
{
    use InteractsWithRooms;

    private function report(array $room, string $state, float $position): void
    {
        $this->postJson("/api/v1/rooms/{$room['code']}/playback/state", [
            'state' => $state,
            'current_time_seconds' => $position,
        ], $this->headersFor($room['token']))->assertOk();
    }

    public function test_steady_position_reports_are_saved_but_broadcast_once_per_window(): void
    {
        $room = $this->createRoom();
        $this->addSong($room['code'], $room['token'], $this->song()->id)->assertSuccessful();
        Event::fake([PlaybackStateChangedEvent::class]);

        $this->report($room, 'playing', 5); // first report of the song: guests are waiting on it
        $this->travel(4)->seconds();
        $this->report($room, 'playing', 9); // exactly where playback should be: inside the window, not broadcast
        $this->travel(4)->seconds();
        $this->report($room, 'playing', 13);
        Event::assertDispatchedTimes(PlaybackStateChangedEvent::class, 1);

        $this->travel(4)->seconds(); // 12s since the last broadcast, past the 10s window
        $this->report($room, 'playing', 17);
        Event::assertDispatchedTimes(PlaybackStateChangedEvent::class, 2);
    }

    public function test_state_changes_and_seeks_broadcast_immediately(): void
    {
        $room = $this->createRoom();
        $this->addSong($room['code'], $room['token'], $this->song()->id)->assertSuccessful();
        $this->report($room, 'playing', 5);
        Event::fake([PlaybackStateChangedEvent::class]);

        $this->travel(2)->seconds();
        $this->report($room, 'paused', 7);
        Event::assertDispatchedTimes(PlaybackStateChangedEvent::class, 1);

        $this->report($room, 'playing', 7);
        Event::assertDispatchedTimes(PlaybackStateChangedEvent::class, 2);

        $this->report($room, 'playing', 90); // seek far from where playback should be
        Event::assertDispatchedTimes(PlaybackStateChangedEvent::class, 3);
    }
}
