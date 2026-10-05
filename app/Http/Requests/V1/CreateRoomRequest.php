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
            'room_name' => 'required|string|min:2|max:100',
            'nickname' => 'required|string|min:1|max:50',
            'device_uuid' => 'nullable|string|max:100',
        ];
    }
}
