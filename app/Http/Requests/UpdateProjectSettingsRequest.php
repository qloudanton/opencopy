<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project && $this->user()->can('update', $project);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Content Generation
            'default_ai_provider_id' => [
                'nullable',
                'integer',
                Rule::exists('ai_providers', 'id')->where('user_id', $this->user()->id),
            ],
            'default_word_count' => ['required', 'integer', 'min:500', 'max:5000'],
            'default_tone' => [
                'required',
                'string',
                Rule::in(['professional', 'casual', 'friendly', 'technical', 'authoritative', 'conversational']),
            ],
            'target_audiences' => ['nullable', 'array'],
            'target_audiences.*' => ['string', 'max:200'],
            'brand_guidelines' => ['nullable', 'string', 'max:2000'],

            // SEO Preferences
            'primary_language' => [
                'required',
                'string',
                Rule::in(['en', 'es', 'fr', 'de', 'pt', 'it', 'nl', 'pl', 'ru', 'zh', 'ja', 'ko']),
            ],
            'target_region' => [
                'nullable',
                'string',
                Rule::in(['global', 'us', 'uk', 'ca', 'au', 'de', 'fr', 'es', 'br', 'in', 'jp', 'kr', 'cn']),
            ],
            'internal_links_per_article' => ['required', 'integer', 'min:0', 'max:10'],

            // Engagement - Brand & Visual
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'image_style' => [
                'nullable',
                'string',
                Rule::in(['illustration', 'sketch', 'watercolor', 'cinematic', 'brand-text']),
            ],

            // Engagement - Content Enhancements
            'include_youtube_videos' => ['boolean'],
            'include_emojis' => ['boolean'],
            'include_infographic_placeholders' => ['boolean'],

            // Engagement - Call-to-Action
            'include_cta' => ['boolean'],
            'cta_product_name' => ['nullable', 'string', 'max:100'],
            'cta_website_url' => ['nullable', 'url', 'max:255'],
            'cta_features' => ['nullable', 'string', 'max:500'],
            'cta_action_text' => ['nullable', 'string', 'max:100'],

            // Sitemap/Internal Linking
            'sitemap_url' => ['nullable', 'url', 'max:500'],
            'auto_internal_linking' => ['boolean'],
            'prioritize_blog_links' => ['boolean'],
            'cross_link_articles' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_ai_provider_id.exists' => 'The selected AI provider is invalid or does not belong to you.',
            'default_word_count.min' => 'Word count must be at least 500 words.',
            'default_word_count.max' => 'Word count cannot exceed 5000 words.',
            'internal_links_per_article.max' => 'You can include a maximum of 10 internal links per article.',
        ];
    }
}
