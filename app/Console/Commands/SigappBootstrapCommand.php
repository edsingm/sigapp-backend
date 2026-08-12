<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SigappBootstrapCommand extends Command
{
    use RunsSigappDeploySteps;

    protected $signature = 'sigapp:bootstrap
        {--force : Reexecuta o seed mesmo se o banco já estiver inicializado}
        {--no-cache : Não recria caches de config/rotas/views}';

    protected $description = 'Bootstrap inicial de ambiente vazio: migrate central + seed. Não use em produção já populada.';

    public function handle(): int
    {
        if ($this->alreadyBootstrapped()) {
            if (! (bool) $this->option('force')) {
                $this->error('O banco já está inicializado. Use sigapp:release nos deploys seguintes, ou --force para reexecutar o seed.');

                return self::FAILURE;
            }

            if ($this->input->isInteractive()
                && ! $this->confirm('O banco já está inicializado. Reexecutar o seed pode alterar dados existentes. Continuar?')) {
                $this->warn('Bootstrap cancelado.');

                return self::SUCCESS;
            }
        }

        return $this->runSteps([
            ['migrate', ['--force' => true]],
            ['db:seed', ['--force' => true]],
            ...$this->cacheSteps(),
        ]);
    }

    private function alreadyBootstrapped(): bool
    {
        $connection = $this->centralConnection();

        if (! Schema::connection($connection)->hasTable('plans')) {
            return false;
        }

        return DB::connection($connection)->table('plans')->exists();
    }

    private function centralConnection(): string
    {
        $connection = config('tenancy.database.central_connection');

        if (is_string($connection) && $connection !== '') {
            return $connection;
        }

        $default = config('database.default');

        return is_string($default) && $default !== '' ? $default : 'sqlite';
    }
}
