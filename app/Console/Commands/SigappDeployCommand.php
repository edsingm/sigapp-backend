<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SigappDeployCommand extends Command
{
    protected $signature = 'sigapp:deploy';

    protected $description = 'Gate de startup: serializa bootstrap/release e só libera o processo após schema convergente';

    public function handle(): int
    {
        $result = Cache::lock('deploy:sigapp-schema', 900)->block(600, function (): int {
            if ($this->isEmptyEnvironment()) {
                $bootstrap = $this->call('sigapp:bootstrap', ['--no-cache' => true]);

                return $bootstrap === self::SUCCESS
                    ? $this->call('sigapp:schema-status', ['--fail-on-drift' => true])
                    : $bootstrap;
            }

            return $this->call('sigapp:release', ['--no-cache' => true]);
        });

        return is_int($result) ? $result : self::FAILURE;
    }

    private function isEmptyEnvironment(): bool
    {
        $connection = config('tenancy.database.central_connection');
        $connection = is_string($connection) && $connection !== '' ? $connection : DB::getDefaultConnection();

        return ! Schema::connection($connection)->hasTable('plans')
            || ! DB::connection($connection)->table('plans')->exists();
    }
}
