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
            'current_time_seconds' => 'nullable|numeric|min:0',
            'queue_item_id' => 'nullable|integer',
            // Server-clock ms at which current_time_seconds was true (host converts via GET /time)
            'position_at_ms' => 'nullable|integer|min:0',
        ];
    }
}
