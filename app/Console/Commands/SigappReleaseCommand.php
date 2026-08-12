<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SigappReleaseCommand extends Command
{
    use RunsSigappDeploySteps;

    protected $signature = 'sigapp:release
        {--no-cache : Não recria caches de config/rotas/views}';

    protected $description = 'Release de deploy: migrate central + tenants. Nunca executa seeders.';

    public function handle(): int
    {
        return $this->runSteps([
            ['migrate', ['--force' => true]],
            ['tenants:migrate'],
            ...$this->cacheSteps(),
        ]);
    }
}
