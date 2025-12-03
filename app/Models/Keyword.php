<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Observers\KeywordObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy(KeywordObserver::class)]
class Keyword extends Model
{
    /** @use HasFactory<\Database\Factories\KeywordFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'keyword',
        'secondary_keywords',
        'search_intent',
        'difficulty',
        'volume',
        'target_word_count',
        'tone',
        'additional_instructions',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'secondary_keywords' => 'array',
            'target_word_count' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function scheduledContents(): HasMany
    {
        return $this->hasMany(ScheduledContent::class);
    }

    /**
     * Get the primary scheduled content for this keyword (most recent).
     */
    public function scheduledContent(): HasOne
    {
        return $this->hasOne(ScheduledContent::class)->latestOfMany();
    }

    public function latestArticle(): ?Article
    {
        return $this->articles()->latest()->first();
    }

    /**
     * Check if keyword has no article yet and is not being generated.
     */
    public function isPending(): bool
    {
        return ! $this->hasArticle() && ! $this->isGenerating();
    }

    /**
     * Check if keyword has at least one article.
     */
    public function hasArticle(): bool
    {
        return $this->articles()->exists();
    }

    /**
     * Check if keyword has at least one article (alias for hasArticle).
     */
    public function isCompleted(): bool
    {
        return $this->hasArticle();
    }

    /**
     * Check if the latest generation attempt failed.
     */
    public function isFailed(): bool
    {
        $content = $this->scheduledContent;

        return $content && $content->status === ContentStatus::Failed;
    }

    /**
     * Check if article generation is currently in progress (queued or generating).
     */
    public function isGenerating(): bool
    {
        $content = $this->scheduledContent;

        return $content && in_array($content->status, [ContentStatus::Queued, ContentStatus::Generating]);
    }

    /**
     * Get the error message from the scheduled content if generation failed.
     */
    public function getErrorMessage(): ?string
    {
        $content = $this->scheduledContent;

        return $content?->error_message;
    }
}
