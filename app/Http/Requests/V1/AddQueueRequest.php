<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class AddQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The Idempotency-Key header is validated like a field, so a bad key reports errors.idempotency_key
        if ($this->hasHeader('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }

    public function rules(): array
    {
        return [
            'song_id' => 'required|exists:songs,id',
            'singer_name' => 'nullable|string|max:50',
            'idempotency_key' => 'nullable|string|regex:/^[A-Za-z0-9-]{8,64}$/',
        ];
    }
}
