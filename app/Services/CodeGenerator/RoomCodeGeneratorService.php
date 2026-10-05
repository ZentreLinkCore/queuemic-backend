<?php

namespace App\Services\CodeGenerator;

use App\Models\Room;

class RoomCodeGeneratorService
{
    /**
     * Generate a unique 6-character alphanumeric room code.
     */
    public static function generate(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Exclude ambiguous 0, O, 1, I
        $maxAttempts = 10;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = '';
            for ($j = 0; $j < 6; $j++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }

            if (!Room::where('code', $code)->where('status', 'active')->exists()) {
                return $code;
            }
        }

        return strtoupper(substr(uniqid(), -6));
    }
}
