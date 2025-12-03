<?php

namespace App\Models;

use App\Observers\KeywordObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'status',
        'priority',
        'error_message',
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

    public function latestArticle(): ?Article
    {
        return $this->articles()->latest()->first();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isGenerating(): bool
    {
        return in_array($this->status, ['queued', 'generating']);
    }
}
