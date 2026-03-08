<?php

namespace App\Enums\Common;

enum RolesEnum: string
{
    case ADMIN = 'ADMIN';
    case DIRECTOR = 'DIRECTOR';
    case MANAGER = 'MANAGER';
    case SUPERVISOR = 'SUPERVISOR';
    case ANALYST = 'ANALYST';
    case USER = 'USER';
}
