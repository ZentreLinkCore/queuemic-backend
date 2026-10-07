<?php

namespace Tests\Feature;

use Tests\Feature\Concerns\InteractsWithRooms;
use Tests\TestCase;

class QueueLimitTest extends TestCase
{
    use InteractsWithRooms;

    public function test_only_the_host_can_change_settings_and_the_limit_is_validated(): void
    {
        $room = $this->createRoom();
        $sam = $this->joinRoom($room['code']);
        $url = "/api/v1/rooms/{$room['code']}/settings";

        $this->patchJson($url, ['max_queue_per_user' => 2], $this->headersFor($sam['token']))
            ->assertForbidden()
            ->assertJsonPath('code', 'HOST_PERMISSION_REQUIRED');

        foreach ([0, 51, 'x'] as $bad) {
            $this->patchJson($url, ['max_queue_per_user' => $bad], $this->headersFor($room['token']))
                ->assertStatus(422)
                ->assertJsonValidationErrors('max_queue_per_user');
        }

        $this->patchJson($url, ['max_queue_per_user' => 2], $this->headersFor($room['token']))
            ->assertOk()
            ->assertJsonPath('data.settings.max_queue_per_user', 2);
    }

    public function test_a_guest_at_the_limit_cannot_queue_another_song(): void
    {
        [$room, $sam] = $this->roomWithLimit(2);
        $this->addSong($room['code'], $sam['token'], $this->song('One')->id)->assertCreated();
        $this->addSong($room['code'], $sam['token'], $this->song('Two')->id)->assertCreated();

        $this->addSong($room['code'], $sam['token'], $this->song('Three')->id)
            ->assertStatus(422)
            ->assertJsonPath('code', 'QUEUE_LIMIT_REACHED')
            ->assertJsonPath('message', 'You already have 2 songs waiting. Wait for one to play before adding more.');
    }

    public function test_the_host_is_exempt_from_the_limit(): void
    {
        [$room] = $this->roomWithLimit(1);

        foreach (['One', 'Two', 'Three'] as $title) {
            $this->addSong($room['code'], $room['token'], $this->song($title)->id)->assertCreated();
        }
    }

    public function test_clearing_the_limit_lets_the_guest_add_again(): void
    {
        [$room, $sam] = $this->roomWithLimit(1);
        $this->addSong($room['code'], $sam['token'], $this->song('One')->id)->assertCreated();
        $this->addSong($room['code'], $sam['token'], $this->song('Two')->id)->assertStatus(422);

        $this->patchJson("/api/v1/rooms/{$room['code']}/settings", ['max_queue_per_user' => null], $this->headersFor($room['token']))
            ->assertOk();

        $this->addSong($room['code'], $sam['token'], $this->song('Two')->id)->assertCreated();
    }

    public function test_replaying_an_accepted_add_is_not_blocked_by_the_limit(): void
    {
        [$room, $sam] = $this->roomWithLimit(1);
        $song = $this->song();
        $first = $this->addSong($room['code'], $sam['token'], $song->id, ['Idempotency-Key' => 'k-limit-0001'])->assertCreated();

        $this->addSong($room['code'], $sam['token'], $song->id, ['Idempotency-Key' => 'k-limit-0001'])
            ->assertCreated()
            ->assertJsonPath('data.id', $first->json('data.id'));
    }

    /**
     * Host's own song fills the stage, so the guest's songs below all wait as pending.
     */
    private function roomWithLimit(int $limit): array
    {
        $room = $this->createRoom();
        $sam = $this->joinRoom($room['code']);
        $this->addSong($room['code'], $room['token'], $this->song('On stage')->id)->assertCreated();
        $this->patchJson("/api/v1/rooms/{$room['code']}/settings", ['max_queue_per_user' => $limit], $this->headersFor($room['token']))
            ->assertOk();

        return [$room, $sam];
    }
}
