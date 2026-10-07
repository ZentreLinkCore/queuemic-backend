<?php

namespace Tests\Feature;

use App\Models\KaraokeQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\Feature\Concerns\InteractsWithRooms;
use Tests\TestCase;

class QueueIdempotencyTest extends TestCase
{
    use InteractsWithRooms;

    private const KEY = 'k-123e4567-e89b';

    public function test_replaying_a_key_returns_the_original_item_without_a_new_row(): void
    {
        $room = $this->createRoom();
        $sam = $this->joinRoom($room['code']);
        $song = $this->song();

        $first = $this->addSong($room['code'], $sam['token'], $song->id, ['Idempotency-Key' => self::KEY])->assertCreated();
        $this->travel(10)->minutes(); // well past the 5-second double-tap window

        $this->addSong($room['code'], $sam['token'], $song->id, ['Idempotency-Key' => self::KEY])
            ->assertCreated()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, KaraokeQueue::where('room_id', $room['room_id'])->count());
        $this->assertDatabaseCount('activity_logs', 3); // room_created, member_joined, one song_queued
    }

    public function test_reusing_a_key_for_a_different_song_or_singer_is_a_conflict(): void
    {
        $room = $this->createRoom();
        $sam = $this->joinRoom($room['code']);
        $this->addSong($room['code'], $sam['token'], $this->song('Zombie')->id, ['Idempotency-Key' => self::KEY])->assertCreated();

        $this->addSong($room['code'], $sam['token'], $this->song('Perfect')->id, ['Idempotency-Key' => self::KEY])
            ->assertStatus(409)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_MISMATCH')
            ->assertJsonPath('success', false);

        $this->assertSame(1, KaraokeQueue::where('room_id', $room['room_id'])->count());
    }

    public function test_malformed_keys_are_rejected(): void
    {
        $room = $this->createRoom();
        $song = $this->song();

        foreach (['ab', 'bad key!!', str_repeat('a', 65)] as $badKey) {
            $this->addSong($room['code'], $room['token'], $song->id, ['Idempotency-Key' => $badKey])
                ->assertStatus(422)
                ->assertJsonPath('code', 'VALIDATION_FAILED')
                ->assertJsonValidationErrors('idempotency_key');
        }
    }

    public function test_the_database_rejects_a_second_row_with_the_same_key(): void
    {
        $room = $this->createRoom();
        $song = $this->song();
        $this->addSong($room['code'], $room['token'], $song->id, ['Idempotency-Key' => self::KEY])->assertCreated();

        // The race path in AddSongToQueueAction relies on exactly this exception
        $this->expectException(UniqueConstraintViolationException::class);
        KaraokeQueue::create([
            'room_id' => $room['room_id'],
            'song_id' => $song->id,
            'requested_by_member_id' => $room['member_id'],
            'idempotency_key' => self::KEY,
            'singer_name' => 'Alex',
            'position' => 9,
            'status' => 'pending',
        ]);
    }

    public function test_a_keyless_repeat_is_merged_only_within_five_seconds(): void
    {
        $room = $this->createRoom();
        $sam = $this->joinRoom($room['code']);
        $this->addSong($room['code'], $room['token'], $this->song('Stage')->id)->assertCreated(); // fills the stage
        $song = $this->song();

        $first = $this->addSong($room['code'], $sam['token'], $song->id)->assertCreated();
        $this->addSong($room['code'], $sam['token'], $song->id)->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));

        $this->travel(6)->seconds();
        $this->addSong($room['code'], $sam['token'], $song->id)->assertCreated();

        $this->assertSame(2, KaraokeQueue::where('room_id', $room['room_id'])->where('song_id', $song->id)->count());
    }
}
