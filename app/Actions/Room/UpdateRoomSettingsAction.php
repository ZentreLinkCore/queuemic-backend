<?php

namespace App\Actions\Room;

use App\Models\Room;
use App\Events\RoomSettingsUpdatedEvent;
use Illuminate\Support\Facades\DB;

class UpdateRoomSettingsAction
{
    public static function execute(Room $room, array $data): Room
    {
        return DB::transaction(function () use ($room, $data) {
            if (isset($data['room_name']) && !empty(trim($data['room_name']))) {
                $room->name = trim($data['room_name']);
            }

            $currentSettings = $room->settings ?? [];

            $settingKeys = [
                'allow_guest_reorder',
                'allow_guest_playback_control',
                'max_queue_per_user',
            ];

            foreach ($settingKeys as $key) {
                if (array_key_exists($key, $data)) {
                    $currentSettings[$key] = $data[$key];
                }
            }

            $room->settings = $currentSettings;
            $room->save();

            try {
                broadcast(new RoomSettingsUpdatedEvent($room));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Room settings update broadcast failed: ' . $e->getMessage());
            }

            return $room;
        });
    }
}
