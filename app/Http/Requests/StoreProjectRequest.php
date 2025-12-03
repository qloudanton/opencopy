<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'website_url' => ['required', 'url', 'max:500'],
            'domain' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'primary_language' => ['nullable', 'string', 'max:50'],
            'target_region' => ['nullable', 'string', 'max:100'],
            'target_audiences' => ['nullable', 'array', 'max:10'],
            'target_audiences.*' => ['string', 'max:255'],
            'competitors' => ['nullable', 'array', 'max:10'],
            'competitors.*' => ['string', 'max:255'],
            'keywords' => ['nullable', 'array', 'max:20'],
            'keywords.*.keyword' => ['required_with:keywords', 'string', 'max:255'],
            'keywords.*.search_intent' => ['nullable', 'string', 'in:informational,commercial,transactional,navigational'],
            'keywords.*.difficulty' => ['nullable', 'string', 'in:low,medium,high'],
            'keywords.*.volume' => ['nullable', 'string', 'in:low,medium,high'],
            // Content preferences
            'default_tone' => ['nullable', 'string', 'in:professional,casual,technical,friendly,authoritative'],
            'default_word_count' => ['nullable', 'integer', 'min:500', 'max:5000'],
            'include_cta' => ['nullable', 'boolean'],
            'cta_product_name' => ['nullable', 'string', 'max:255'],
            'cta_website_url' => ['nullable', 'url', 'max:500'],
            'cta_action_text' => ['nullable', 'string', 'max:100'],
            // Legacy settings (keep for backwards compatibility)
            'settings' => ['nullable', 'array'],
            'settings.default_tone' => ['nullable', 'string', 'in:professional,casual,technical,friendly'],
            'settings.default_word_count' => ['nullable', 'integer', 'min:500', 'max:10000'],
            'settings.default_search_intent' => ['nullable', 'string', 'in:informational,transactional,navigational,commercial'],
            'settings.language' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please provide a name for your project.',
            'website_url.required' => 'Please provide your website URL.',
            'website_url.url' => 'Please provide a valid URL (e.g., https://example.com).',
            'settings.default_word_count.min' => 'Word count must be at least 500.',
            'settings.default_word_count.max' => 'Word count cannot exceed 10,000.',
        ];
    }
}
