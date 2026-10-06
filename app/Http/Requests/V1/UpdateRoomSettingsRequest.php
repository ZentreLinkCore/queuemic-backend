<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_name' => ['nullable', 'string', 'max:100'],
            'allow_guest_reorder' => ['nullable', 'boolean'],
            'allow_explicit' => ['nullable', 'boolean'],
            'allow_guest_playback_control' => ['nullable', 'boolean'],
            'max_queue_per_user' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
