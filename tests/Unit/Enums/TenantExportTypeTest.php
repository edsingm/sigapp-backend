<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\TenantExportType;
use App\Models\Tenant\User;
use Tests\TestCase;

class TenantExportTypeTest extends TestCase
{
    public function test_subject_portability_is_a_json_privacy_export(): void
    {
        $type = TenantExportType::SUBJECT_PORTABILITY;

        $this->assertFalse($type->requiresSubject());
        $this->assertFalse($type->acceptsFilters());
        $this->assertFalse($type->acceptsPayload());
        $this->assertFalse($type->isOperationalExport());
        $this->assertSame('json', $type->extension());
        $this->assertSame('application/json', $type->mimeType());
        $this->assertSame(User::class, $type->authorizableModel());
        $this->assertFalse(TenantExportType::TENANT_PORTABILITY->isOperationalExport());
    }
}
