<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * @mixin Command
 */
trait RunsSigappDeploySteps
{
    /**
     * @param  list<array{0: string, 1?: array<string, mixed>}>  $steps
     */
    private function runSteps(array $steps): int
    {
        foreach ($steps as $step) {
            $command = $step[0];
            $arguments = $step[1] ?? [];
            $exitCode = $this->call($command, $arguments);

            if ($exitCode !== self::SUCCESS) {
                $this->error("Falha ao executar `{$command}` (exit {$exitCode}).");

                return $exitCode;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1?: array<string, mixed>}>
     */
    private function cacheSteps(): array
    {
        if ((bool) $this->option('no-cache') || app()->environment('testing')) {
            $this->comment('Caches de config/rotas/views omitidos.');

            return [];
        }

        return [
            ['config:cache'],
            ['route:cache'],
            ['view:cache'],
        ];
    }
}
