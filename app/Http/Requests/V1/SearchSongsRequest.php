<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SearchSongsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Blank means "trending karaoke hits"; anything typed must be a real query
            'q' => 'nullable|string|min:2|max:100',
            'limit' => 'nullable|integer|min:1|max:50',
        ];
    }
}
