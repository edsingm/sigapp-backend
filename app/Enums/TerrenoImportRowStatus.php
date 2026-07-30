<?php

declare(strict_types=1);

namespace App\Enums;

enum TerrenoImportRowStatus: string
{
    case VALID = 'valid';
    case INVALID = 'invalid';
    case IMPORTED = 'imported';
}
