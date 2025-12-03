<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'default_text_provider_id',
        'default_image_provider_id',
        'youtube_api_key',
        'settings',
    ];

    protected $hidden = [
        'youtube_api_key',
    ];

    protected function casts(): array
    {
        return [
            'youtube_api_key' => 'encrypted',
            'settings' => 'array',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultTextProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'default_text_provider_id');
    }

    public function defaultImageProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'default_image_provider_id');
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Get a specific setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set a specific setting value.
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
    }

    /**
     * Check if YouTube API key is configured.
     */
    public function hasYouTubeApiKey(): bool
    {
        return ! empty($this->youtube_api_key);
    }
}
