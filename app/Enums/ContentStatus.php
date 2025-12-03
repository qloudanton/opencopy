<?php

namespace App\Enums;

enum ContentStatus: string
{
    case Backlog = 'backlog';
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Generating = 'generating';
    case Enriching = 'enriching';
    case InReview = 'in_review';
    case Approved = 'approved';
    case PublishingQueued = 'publishing_queued';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Backlog => 'Backlog',
            self::Scheduled => 'Scheduled',
            self::Queued => 'Queued',
            self::Generating => 'Generating',
            self::Enriching => 'Enriching',
            self::InReview => 'In Review',
            self::Approved => 'Approved',
            self::PublishingQueued => 'Publishing',
            self::Published => 'Published',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Backlog => 'slate',
            self::Scheduled => 'blue',
            self::Queued => 'amber',
            self::Generating => 'yellow',
            self::Enriching => 'purple',
            self::InReview => 'orange',
            self::Approved => 'green',
            self::PublishingQueued => 'blue',
            self::Published => 'emerald',
            self::Failed => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Backlog => 'inbox',
            self::Scheduled => 'calendar',
            self::Queued => 'clock',
            self::Generating => 'sparkles',
            self::Enriching => 'wand',
            self::InReview => 'eye',
            self::Approved => 'check-circle',
            self::PublishingQueued => 'clock',
            self::Published => 'globe',
            self::Failed => 'x-circle',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Backlog, self::Scheduled, self::InReview, self::Approved, self::Failed]);
    }

    public function isTerminal(): bool
    {
        return $this === self::Published;
    }

    public function canTransitionTo(ContentStatus $status): bool
    {
        return match ($this) {
            self::Backlog => in_array($status, [self::Scheduled]),
            self::Scheduled => in_array($status, [self::Backlog, self::Queued]),
            self::Queued => in_array($status, [self::Generating, self::Failed]),
            self::Generating => in_array($status, [self::Enriching, self::InReview, self::Failed]),
            self::Enriching => in_array($status, [self::InReview, self::Approved, self::Failed]),
            self::InReview => in_array($status, [self::Enriching, self::Approved, self::Scheduled]),
            self::Approved => in_array($status, [self::Enriching, self::Published, self::InReview]),
            self::PublishingQueued => in_array($status, [self::Published]),
            self::Published => false,
            self::Failed => in_array($status, [self::Scheduled, self::Backlog]),
        };
    }

    /**
     * @return array<string, self>
     */
    public static function pipelineStages(): array
    {
        return [
            'backlog' => self::Backlog,
            'scheduled' => self::Scheduled,
            'queued' => self::Queued,
            'generating' => self::Generating,
            'enriching' => self::Enriching,
            'in_review' => self::InReview,
            'approved' => self::Approved,
            'publishing_queued' => self::PublishingQueued,
            'published' => self::Published,
            'failed' => self::Failed,
        ];
    }
}
