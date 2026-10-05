<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class AddQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'song_id' => 'required|exists:songs,id',
            'singer_name' => 'nullable|string|max:50',
        ];
    }
}
