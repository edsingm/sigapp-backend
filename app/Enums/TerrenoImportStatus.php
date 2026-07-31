<?php

declare(strict_types=1);

namespace App\Enums;

enum TerrenoImportStatus: string
{
    case QUEUED = 'queued';
    case VALIDATING = 'validating';
    case INVALID = 'invalid';
    case READY = 'ready';
    case IMPORTING = 'importing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
}
