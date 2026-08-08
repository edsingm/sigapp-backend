<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use LogicException;

trait CreatesApplication
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if (
            ! $app->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('tenancy.database.central_connection') !== 'sqlite'
        ) {
            throw new LogicException(
                'Os testes devem usar APP_ENV=testing, conexão central SQLite e banco :memory:. '
                .'Execute "composer test" para limpar o cache de configuração antes da suíte.'
            );
        }

        return $app;
    }
}
