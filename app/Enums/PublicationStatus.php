<?php

namespace App\Enums;

enum PublicationStatus: string
{
    case Pending = 'pending';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Publishing => 'Publishing',
            self::Published => 'Published',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Publishing => 'blue',
            self::Published => 'green',
            self::Failed => 'red',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Published, self::Failed]);
    }
}
