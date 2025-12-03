<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledContent extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduledContentFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'keyword_id',
        'article_id',
        'title',
        'content_type',
        'status',
        'previous_status',
        'scheduled_date',
        'scheduled_time',
        'position',
        'target_word_count',
        'tone',
        'custom_instructions',
        'notes',
        'generation_attempts',
        'error_message',
        'generation_started_at',
        'generation_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'content_type' => ContentType::class,
            'status' => ContentStatus::class,
            'scheduled_date' => 'date',
            'scheduled_time' => 'datetime:H:i',
            'position' => 'integer',
            'target_word_count' => 'integer',
            'generation_attempts' => 'integer',
            'generation_started_at' => 'datetime',
            'generation_completed_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeWithStatus(Builder $query, ContentStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeScheduledBetween(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()]);
    }

    public function scopeScheduledOn(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('scheduled_date', $date->toDateString());
    }

    public function scopeInBacklog(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Backlog);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Scheduled);
    }

    public function scopeReadyToGenerate(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Scheduled)
            ->whereNotNull('scheduled_date')
            ->whereDate('scheduled_date', '<=', now()->addDays(1));
    }

    public function scopeInReview(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::InReview);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Approved);
    }

    public function scopeOrderBySchedule(Builder $query): Builder
    {
        return $query->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->orderBy('position');
    }

    // =========================================================================
    // Status Helpers
    // =========================================================================

    public function isInBacklog(): bool
    {
        return $this->status === ContentStatus::Backlog;
    }

    public function isScheduled(): bool
    {
        return $this->status === ContentStatus::Scheduled;
    }

    public function isQueued(): bool
    {
        return $this->status === ContentStatus::Queued;
    }

    public function isGenerating(): bool
    {
        return $this->status === ContentStatus::Generating;
    }

    public function isInReview(): bool
    {
        return $this->status === ContentStatus::InReview;
    }

    public function isApproved(): bool
    {
        return $this->status === ContentStatus::Approved;
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    public function isFailed(): bool
    {
        return $this->status === ContentStatus::Failed;
    }

    public function canEdit(): bool
    {
        return $this->status->isEditable();
    }

    public function canTransitionTo(ContentStatus $status): bool
    {
        return $this->status->canTransitionTo($status);
    }

    // =========================================================================
    // Actions
    // =========================================================================

    public function schedule(Carbon $date, ?string $time = null): self
    {
        $this->update([
            'scheduled_date' => $date,
            'scheduled_time' => $time,
            'status' => ContentStatus::Scheduled,
        ]);

        return $this;
    }

    public function moveToBacklog(): self
    {
        $this->update([
            'scheduled_date' => null,
            'scheduled_time' => null,
            'status' => ContentStatus::Backlog,
        ]);

        return $this;
    }

    public function startGeneration(): self
    {
        $this->update([
            'status' => ContentStatus::Generating,
            'generation_started_at' => now(),
            'generation_attempts' => $this->generation_attempts + 1,
            'error_message' => null,
        ]);

        return $this;
    }

    public function completeGeneration(Article $article): self
    {
        $this->update([
            'article_id' => $article->id,
            'status' => ContentStatus::InReview,
            'generation_completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Start the enrichment phase (adding images, links, etc.)
     * Stores the previous status to restore after enrichment completes.
     */
    public function startEnriching(): self
    {
        // Store the current status to restore later (only if not already enriching)
        if ($this->status !== ContentStatus::Enriching) {
            $this->update([
                'status' => ContentStatus::Enriching,
                'previous_status' => $this->status->value,
            ]);
        }

        return $this;
    }

    /**
     * Complete the enrichment phase and restore the previous status.
     */
    public function completeEnriching(): self
    {
        // Restore the previous status, or default to InReview
        $previousStatus = $this->previous_status
            ? ContentStatus::from($this->previous_status)
            : ContentStatus::InReview;

        $this->update([
            'status' => $previousStatus,
            'previous_status' => null,
        ]);

        return $this;
    }

    public function failGeneration(string $error): self
    {
        $this->update([
            'status' => ContentStatus::Failed,
            'error_message' => $error,
            'generation_completed_at' => now(),
        ]);

        return $this;
    }

    public function approve(): self
    {
        $this->update(['status' => ContentStatus::Approved]);

        return $this;
    }

    public function publish(): self
    {
        $this->update(['status' => ContentStatus::Published]);

        return $this;
    }

    public function reschedule(Carbon $date, ?string $time = null): self
    {
        if ($this->status === ContentStatus::Failed) {
            $this->update([
                'scheduled_date' => $date,
                'scheduled_time' => $time,
                'status' => ContentStatus::Scheduled,
                'error_message' => null,
            ]);
        } else {
            $this->update([
                'scheduled_date' => $date,
                'scheduled_time' => $time,
            ]);
        }

        return $this;
    }

    // =========================================================================
    // Computed Properties
    // =========================================================================

    public function getDisplayTitleAttribute(): string
    {
        return $this->title ?? $this->keyword?->keyword ?? 'Untitled';
    }

    public function getEffectiveWordCountAttribute(): int
    {
        return $this->target_word_count
            ?? $this->project?->default_word_count
            ?? 1500;
    }

    public function getEffectiveToneAttribute(): ?string
    {
        return $this->tone ?? $this->project?->default_tone;
    }

    public function getScheduledDateTimeAttribute(): ?Carbon
    {
        if (! $this->scheduled_date) {
            return null;
        }

        if ($this->scheduled_time) {
            return $this->scheduled_date->setTimeFromTimeString($this->scheduled_time->format('H:i:s'));
        }

        return $this->scheduled_date;
    }

    public function isOverdue(): bool
    {
        if (! $this->scheduled_date || $this->isPublished()) {
            return false;
        }

        return $this->scheduled_date->isPast();
    }

    public function isDueToday(): bool
    {
        return $this->scheduled_date?->isToday() ?? false;
    }

    public function isDueTomorrow(): bool
    {
        return $this->scheduled_date?->isTomorrow() ?? false;
    }
}
