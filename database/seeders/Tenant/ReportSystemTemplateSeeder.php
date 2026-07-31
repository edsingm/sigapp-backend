<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Services\Tenant\ReportTemplateService;
use Illuminate\Database\Seeder;

class ReportSystemTemplateSeeder extends Seeder
{
    public function run(): void
    {
        app(ReportTemplateService::class)->ensureSystemTemplates();
    }
}
