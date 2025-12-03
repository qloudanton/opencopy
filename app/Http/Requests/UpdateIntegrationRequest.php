<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $integration = $this->route('integration');
        $type = $integration?->type ?? $this->input('type');

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        // Add type-specific validation rules (all nullable for updates)
        match ($type) {
            'webhook' => $rules = array_merge($rules, $this->webhookRules()),
            'wordpress' => $rules = array_merge($rules, $this->wordPressRules()),
            'webflow' => $rules = array_merge($rules, $this->webflowRules()),
            'shopify' => $rules = array_merge($rules, $this->shopifyRules()),
            'wix' => $rules = array_merge($rules, $this->wixRules()),
            default => null,
        };

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookRules(): array
    {
        return [
            'endpoint_url' => ['nullable', 'url:https'],
            'access_token' => ['nullable', 'string', 'min:8'],
            'timeout' => ['sometimes', 'integer', 'min:5', 'max:120'],
            'retry_times' => ['sometimes', 'integer', 'min:0', 'max:5'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function wordPressRules(): array
    {
        return [
            'site_url' => ['nullable', 'url'],
            'username' => ['nullable', 'string', 'min:1'],
            'application_password' => ['nullable', 'string', 'min:8'],
            'default_status' => ['sometimes', 'string', Rule::in(['draft', 'publish', 'pending'])],
            'default_category' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function webflowRules(): array
    {
        return [
            'api_token' => ['nullable', 'string', 'min:8'],
            'site_id' => ['nullable', 'string', 'min:1'],
            'collection_id' => ['nullable', 'string', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shopifyRules(): array
    {
        return [
            'store_url' => ['nullable', 'url'],
            'access_token' => ['nullable', 'string', 'min:8'],
            'blog_id' => ['nullable', 'string', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function wixRules(): array
    {
        return [
            'api_key' => ['nullable', 'string', 'min:8'],
            'site_id' => ['nullable', 'string', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'endpoint_url.url' => 'The webhook URL must be a valid HTTPS URL.',
            'access_token.min' => 'The access token must be at least 8 characters.',
            'site_url.url' => 'Please enter a valid site URL.',
        ];
    }
}
