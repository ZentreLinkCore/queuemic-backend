<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class MoveQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'direction' => 'nullable|in:up,down,top,bottom',
            'target_position' => 'nullable|integer|min:1',
        ];
    }
}
