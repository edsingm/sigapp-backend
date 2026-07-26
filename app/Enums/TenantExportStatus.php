<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantExportStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
