<?php

declare(strict_types=1);

namespace App\Enums;

enum EstrategiaFinanceira: string
{
    case CEF = 'cef';
    case CARTEIRA_PROPRIA = 'carteira_propria';
}
