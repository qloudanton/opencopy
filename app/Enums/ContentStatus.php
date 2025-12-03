<?php

namespace App\Enums;

enum ContentStatus: string
{
    case Backlog = 'backlog';
    case Scheduled = 'scheduled';
    case Generating = 'generating';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Backlog => 'Backlog',
            self::Scheduled => 'Scheduled',
            self::Generating => 'Generating',
            self::InReview => 'In Review',
            self::Approved => 'Approved',
            self::Published => 'Published',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Backlog => 'slate',
            self::Scheduled => 'blue',
            self::Generating => 'yellow',
            self::InReview => 'orange',
            self::Approved => 'green',
            self::Published => 'emerald',
            self::Failed => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Backlog => 'inbox',
            self::Scheduled => 'calendar',
            self::Generating => 'sparkles',
            self::InReview => 'eye',
            self::Approved => 'check-circle',
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
            self::Scheduled => in_array($status, [self::Backlog, self::Generating]),
            self::Generating => in_array($status, [self::InReview, self::Failed]),
            self::InReview => in_array($status, [self::Approved, self::Scheduled]),
            self::Approved => in_array($status, [self::Published, self::InReview]),
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
            'generating' => self::Generating,
            'in_review' => self::InReview,
            'approved' => self::Approved,
            'published' => self::Published,
        ];
    }
}
