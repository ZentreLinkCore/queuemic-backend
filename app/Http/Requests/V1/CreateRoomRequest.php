<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_name' => 'required|string|min:2|max:50',
            // No angle brackets: nicknames are echoed to every device in the room
            'nickname' => 'required|string|min:2|max:30|not_regex:/[<>]/',
            'device_uuid' => 'nullable|string|max:64',
        ];
    }
}
