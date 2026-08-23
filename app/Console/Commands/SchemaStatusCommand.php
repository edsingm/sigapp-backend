<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SchemaCompatibilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SchemaStatusCommand extends Command
{
    protected $signature = 'sigapp:schema-status {--json : Emite JSON} {--fail-on-drift : Retorna exit code não zero quando houver divergência}';

    protected $description = 'Inventaria a convergência das migrations centrais e de todos os tenants';

    public function handle(SchemaCompatibilityService $schemas): int
    {
        $report = $schemas->scan();
        Cache::put('operations:schema-compatibility', array_merge($report, [
            'checked_at' => now()->timestamp,
        ]), now()->addHours(24));

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Campo', 'Valor'], [
                ['compatible', $report['compatible'] ? 'yes' : 'no'],
                ['central_pending', count($report['central_pending'])],
                ['tenants_checked', $report['tenants_checked']],
                ['tenants_drifted', $report['tenants_drifted']],
                ['fingerprint', $report['fingerprint']],
            ]);
        }

        return ! $report['compatible'] && $this->option('fail-on-drift')
            ? self::FAILURE
            : self::SUCCESS;
    }
}
