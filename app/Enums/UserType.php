<?php

namespace App\Enums;

enum UserType: string
{
    case SIGAPP = 'SIGAPP';
    case TENANT = 'TENANT';
}
