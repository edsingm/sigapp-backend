<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class BootstrapCentralAdminCommand extends Command
{
    protected $signature = 'central:bootstrap-admin';

    protected $description = 'Cria ou atualiza o administrador central local a partir das variáveis de ambiente.';

    public function handle(): int
    {
        if (! app()->isLocal()) {
            $this->error('Este comando só pode ser executado em ambiente local.');

            return self::FAILURE;
        }

        $email = trim((string) env('CENTRAL_ADMIN_EMAIL', ''));
        $password = (string) env('CENTRAL_ADMIN_PASSWORD', '');
        $name = trim((string) env('CENTRAL_ADMIN_NAME', 'Admin Central'));

        if ($email === '' || $password === '') {
            $this->error('Defina CENTRAL_ADMIN_EMAIL e CENTRAL_ADMIN_PASSWORD no ambiente local.');

            return self::FAILURE;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'is_admin' => true,
            ]
        );

        $this->info('Administrador central local configurado com sucesso.');

        return self::SUCCESS;
    }
}
