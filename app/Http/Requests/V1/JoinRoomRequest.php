<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class JoinRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_code' => 'required|string|min:4|max:8',
            'nickname' => 'required|string|min:1|max:50',
            'device_uuid' => 'nullable|string|max:100',
        ];
    }
}
