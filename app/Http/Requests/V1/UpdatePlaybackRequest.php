<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlaybackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'state' => 'required|in:playing,paused,buffering,stopped',
            'current_time_seconds' => 'nullable|integer|min:0',
        ];
    }
}
