<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class AppVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => 'required|string|in:android,ios,web',
            // Plain major.minor.patch: version_compare() then orders "1.10.0" after "1.2.0"
            'current_version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/'],
            'current_build' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        // The spec fixes this exact text for a malformed or missing current_version (EC-01)
        return [
            'current_version.required' => 'Invalid semver string format.',
            'current_version.string' => 'Invalid semver string format.',
            'current_version.regex' => 'Invalid semver string format.',
        ];
    }
}
