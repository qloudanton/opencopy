<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'settings' => ['nullable', 'array'],
            'settings.default_tone' => ['nullable', 'string', 'in:professional,casual,technical,friendly'],
            'settings.default_word_count' => ['nullable', 'integer', 'min:500', 'max:10000'],
            'settings.default_search_intent' => ['nullable', 'string', 'in:informational,transactional,navigational,commercial'],
            'settings.language' => ['nullable', 'string', 'max:10'],
            'is_active' => ['boolean'],
        ];
    }
}
