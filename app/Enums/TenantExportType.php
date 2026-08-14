<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;

enum TenantExportType: string
{
    case TERRENOS_PDF = 'terrenos_pdf';
    case TERRENOS_EXCEL = 'terrenos_excel';
    case TERRENO_DETAIL_PDF = 'terreno_detail_pdf';
    case TERRENO_CHECKLIST_PDF = 'terreno_checklist_pdf';
    case VIABILIDADE_PDF = 'viabilidade_pdf';
    case SUBJECT_PORTABILITY = 'subject_portability';
    case TENANT_PORTABILITY = 'tenant_portability';

    public function requiresSubject(): bool
    {
        return match ($this) {
            self::TERRENOS_PDF, self::TERRENOS_EXCEL, self::SUBJECT_PORTABILITY, self::TENANT_PORTABILITY => false,
            default => true,
        };
    }

    public function acceptsFilters(): bool
    {
        return match ($this) {
            self::TERRENOS_PDF, self::TERRENOS_EXCEL => true,
            default => false,
        };
    }

    public function acceptsPayload(): bool
    {
        return $this === self::TERRENO_CHECKLIST_PDF;
    }

    public function feature(): string
    {
        return match ($this) {
            self::TERRENOS_EXCEL => 'exports.excel',
            self::SUBJECT_PORTABILITY, self::TENANT_PORTABILITY => 'privacy.subject_portability',
            default => 'exports.pdf',
        };
    }

    /** @return class-string<Terreno|User|Viabilidade> */
    public function authorizableModel(): string
    {
        return match ($this) {
            self::VIABILIDADE_PDF => Viabilidade::class,
            self::SUBJECT_PORTABILITY, self::TENANT_PORTABILITY => User::class,
            default => Terreno::class,
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::TERRENOS_EXCEL => 'xlsx',
            self::SUBJECT_PORTABILITY, self::TENANT_PORTABILITY => 'json',
            default => 'pdf',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::TERRENOS_EXCEL => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::SUBJECT_PORTABILITY, self::TENANT_PORTABILITY => 'application/json',
            default => 'application/pdf',
        };
    }

    public function isOperationalExport(): bool
    {
        return ! in_array($this, [self::SUBJECT_PORTABILITY, self::TENANT_PORTABILITY], true);
    }
}
