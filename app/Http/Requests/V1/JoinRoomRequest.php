<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class JoinRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // People type codes in any case; validate the canonical uppercase form
        if (is_string($this->input('room_code'))) {
            $this->merge(['room_code' => strtoupper(trim($this->input('room_code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'room_code' => 'required|string|regex:/^[A-Z0-9]{6}$/',
            // No angle brackets: nicknames are echoed to every device in the room
            'nickname' => 'required|string|min:2|max:30|not_regex:/[<>]/',
            'device_uuid' => 'nullable|string|max:64',
        ];
    }
}
