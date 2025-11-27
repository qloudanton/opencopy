<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'domain',
        'description',
        'settings',
        'is_active',
        'default_ai_provider_id',
        'default_word_count',
        'default_tone',
        'target_audience',
        'brand_guidelines',
        'primary_language',
        'target_region',
        'internal_links_per_article',
        // Engagement settings
        'brand_color',
        'image_style',
        'include_youtube_videos',
        'include_emojis',
        'include_infographic_placeholders',
        'include_cta',
        'cta_product_name',
        'cta_website_url',
        'cta_features',
        'cta_action_text',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'default_word_count' => 'integer',
            'internal_links_per_article' => 'integer',
            'include_youtube_videos' => 'boolean',
            'include_emojis' => 'boolean',
            'include_infographic_placeholders' => 'boolean',
            'include_cta' => 'boolean',
        ];
    }

    public function defaultAiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'default_ai_provider_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function internalLinks(): HasMany
    {
        return $this->hasMany(InternalLink::class);
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }
}
