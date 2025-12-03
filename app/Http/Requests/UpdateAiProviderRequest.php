<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['sometimes', 'required', 'string', 'in:openai,anthropic,ollama,groq,mistral,openrouter'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'api_endpoint' => ['nullable', 'url', 'max:500'],
            'model' => ['sometimes', 'required', 'string', 'max:100'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'supports_text' => ['boolean'],
            'supports_image' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.in' => 'Please select a valid AI provider.',
            'api_endpoint.url' => 'Please provide a valid URL for the API endpoint.',
        ];
    }
}
