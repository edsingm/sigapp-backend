<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;

enum TenantExportType: string
{
    case TERRENOS_PDF = 'terrenos_pdf';
    case TERRENOS_EXCEL = 'terrenos_excel';
    case TERRENO_DETAIL_PDF = 'terreno_detail_pdf';
    case TERRENO_CHECKLIST_PDF = 'terreno_checklist_pdf';
    case VIABILIDADE_PDF = 'viabilidade_pdf';

    public function requiresSubject(): bool
    {
        return match ($this) {
            self::TERRENOS_PDF, self::TERRENOS_EXCEL => false,
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
        return $this === self::TERRENOS_EXCEL ? 'exports.excel' : 'exports.pdf';
    }

    /** @return class-string<Terreno|Viabilidade> */
    public function authorizableModel(): string
    {
        return $this === self::VIABILIDADE_PDF ? Viabilidade::class : Terreno::class;
    }

    public function extension(): string
    {
        return $this === self::TERRENOS_EXCEL ? 'xlsx' : 'pdf';
    }

    public function mimeType(): string
    {
        return $this === self::TERRENOS_EXCEL
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/pdf';
    }
}
