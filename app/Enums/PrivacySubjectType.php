<?php

declare(strict_types=1);

namespace App\Enums;

enum PrivacySubjectType: string
{
    case PLATFORM_USER = 'platform_user';
    case TENANT_USER = 'tenant_user';
    case LEAD = 'lead';
    case OTHER = 'other';
}
