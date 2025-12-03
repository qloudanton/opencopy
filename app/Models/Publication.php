<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Publication extends Model
{
    /** @use HasFactory<\Database\Factories\PublicationFactory> */
    use HasFactory;

    protected $fillable = [
        'article_id',
        'integration_id',
        'status',
        'external_id',
        'external_url',
        'payload_sent',
        'response_received',
        'error_message',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_sent' => 'array',
            'response_received' => 'array',
            'published_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    // =========================================================================
    // Status Helpers
    // =========================================================================

    /**
     * Get the status as an enum.
     */
    public function publicationStatus(): ?PublicationStatus
    {
        return PublicationStatus::tryFrom($this->status);
    }

    public function isPublished(): bool
    {
        return $this->status === PublicationStatus::Published->value;
    }

    public function isFailed(): bool
    {
        return $this->status === PublicationStatus::Failed->value;
    }

    public function isPending(): bool
    {
        return $this->status === PublicationStatus::Pending->value;
    }

    public function isPublishing(): bool
    {
        return $this->status === PublicationStatus::Publishing->value;
    }

    /**
     * Check if this publication can be retried.
     */
    public function canRetry(): bool
    {
        return $this->isFailed() && $this->integration !== null;
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Scope to successful publications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Publication>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Publication>
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', PublicationStatus::Published->value);
    }

    /**
     * Scope to failed publications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Publication>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Publication>
     */
    public function scopeFailed($query)
    {
        return $query->where('status', PublicationStatus::Failed->value);
    }

    /**
     * Scope to pending publications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Publication>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Publication>
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [
            PublicationStatus::Pending->value,
            PublicationStatus::Publishing->value,
        ]);
    }
}
