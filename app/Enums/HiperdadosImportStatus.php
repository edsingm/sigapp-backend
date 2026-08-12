<?php

declare(strict_types=1);

namespace App\Enums;

enum HiperdadosImportStatus: string
{
    case Queued = 'queued';
    case Fetching = 'fetching';
    case Ready = 'ready';
    case Committing = 'committing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed => true,
            default => false,
        };
    }

    public function canCommit(): bool
    {
        return $this === self::Ready;
    }
}
