<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKeywordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'required', 'string', 'max:255'],
            'secondary_keywords' => ['nullable', 'array', 'max:20'],
            'secondary_keywords.*' => ['string', 'max:255'],
            'search_intent' => ['nullable', 'string', 'in:informational,transactional,navigational,commercial'],
            'target_word_count' => ['nullable', 'integer', 'min:300', 'max:10000'],
            'tone' => ['nullable', 'string', 'in:professional,casual,technical,friendly,authoritative'],
            'additional_instructions' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.required' => 'Please enter a primary keyword.',
            'secondary_keywords.max' => 'You can add up to 20 secondary keywords.',
            'target_word_count.min' => 'Word count must be at least 300.',
            'target_word_count.max' => 'Word count cannot exceed 10,000.',
            'additional_instructions.max' => 'Additional instructions cannot exceed 2,000 characters.',
        ];
    }
}
