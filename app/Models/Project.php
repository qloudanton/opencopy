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
        'website_url',
        'domain',
        'description',
        'settings',
        'is_active',
        'default_ai_provider_id',
        'default_image_provider_id',
        'default_word_count',
        'default_tone',
        'target_audiences',
        'competitors',
        'brand_guidelines',
        'primary_language',
        'target_region',
        'internal_links_per_article',
        // Media settings
        'brand_color',
        'image_style',
        'generate_inline_images',
        'generate_featured_image',
        'include_youtube_videos',
        'include_emojis',
        'include_infographic_placeholders',
        'include_cta',
        'cta_product_name',
        'cta_website_url',
        'cta_features',
        'cta_action_text',
        // Sitemap/Internal linking settings
        'sitemap_url',
        'auto_internal_linking',
        'prioritize_blog_links',
        'cross_link_articles',
        'sitemap_last_fetched_at',
        // Content calendar settings
        'posts_per_week',
        'publishing_days',
        'default_publish_time',
        'auto_generate_enabled',
        'auto_generate_days_ahead',
        'calendar_view',
        'calendar_start_day',
        // Auto-publish settings
        'auto_publish',
        'skip_review',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'target_audiences' => 'array',
            'competitors' => 'array',
            'is_active' => 'boolean',
            'default_word_count' => 'integer',
            'internal_links_per_article' => 'integer',
            'generate_inline_images' => 'boolean',
            'generate_featured_image' => 'boolean',
            'include_youtube_videos' => 'boolean',
            'include_emojis' => 'boolean',
            'include_infographic_placeholders' => 'boolean',
            'include_cta' => 'boolean',
            'auto_internal_linking' => 'boolean',
            'prioritize_blog_links' => 'boolean',
            'cross_link_articles' => 'boolean',
            'sitemap_last_fetched_at' => 'datetime',
            // Content calendar casts
            'publishing_days' => 'array',
            'posts_per_week' => 'integer',
            'auto_generate_enabled' => 'boolean',
            'auto_generate_days_ahead' => 'integer',
            // Auto-publish casts
            'skip_review' => 'boolean',
        ];
    }

    public function defaultAiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'default_ai_provider_id');
    }

    public function defaultImageProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'default_image_provider_id');
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

    public function pages(): HasMany
    {
        return $this->hasMany(ProjectPage::class);
    }

    public function scheduledContents(): HasMany
    {
        return $this->hasMany(ScheduledContent::class);
    }

    /**
     * Get the default publishing days for the week.
     *
     * @return array<int>
     */
    public function getPublishingDaysArray(): array
    {
        return $this->publishing_days ?? [1, 3, 5]; // Monday, Wednesday, Friday
    }

    /**
     * Get the default publish time formatted.
     */
    public function getFormattedPublishTime(): string
    {
        return $this->default_publish_time ?? '09:00';
    }

    /**
     * Get the effective text AI provider for this project.
     * Follows the cascade: Project → User Account Default → Any Active.
     */
    public function getEffectiveTextProvider(): ?AiProvider
    {
        // 1. First priority: Project's specific text provider
        if ($this->default_ai_provider_id) {
            $provider = $this->defaultAiProvider;
            if ($provider && $provider->is_active && $provider->supports_text) {
                return $provider;
            }
        }

        // 2. Second priority: User's account default text provider
        $provider = $this->user->getDefaultTextProvider();
        if ($provider && $provider->is_active) {
            return $provider;
        }

        // 3. Last resort: Any active text provider
        return $this->user->aiProviders()
            ->where('is_active', true)
            ->where('supports_text', true)
            ->first();
    }

    /**
     * Get the effective image AI provider for this project.
     * Follows the cascade: Project → User Account Default → Any Active.
     */
    public function getEffectiveImageProvider(): ?AiProvider
    {
        // 1. First priority: Project's specific image provider
        if ($this->default_image_provider_id) {
            $provider = $this->defaultImageProvider;
            if ($provider && $provider->is_active && $provider->supports_image) {
                return $provider;
            }
        }

        // 2. Second priority: User's account default image provider
        $provider = $this->user->getDefaultImageProvider();
        if ($provider && $provider->is_active) {
            return $provider;
        }

        // 3. Last resort: Any active image provider
        return $this->user->aiProviders()
            ->where('is_active', true)
            ->where('supports_image', true)
            ->first();
    }
}
