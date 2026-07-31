<?php

declare(strict_types=1);

namespace App\Enums;

enum TerrenoPolygonStatus: string
{
    case PENDING = 'pending';
    case LINKED = 'linked';
}
