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

        $email = trim((string) config('app.central_admin.email'));
        $password = (string) config('app.central_admin.password');
        $name = trim((string) config('app.central_admin.name'));

        if ($email === '' || $password === '') {
            $this->error('Defina CENTRAL_ADMIN_EMAIL e CENTRAL_ADMIN_PASSWORD no ambiente local.');

            return self::FAILURE;
        }

        $admin = User::query()->firstOrNew(['email' => $email]);
        $admin->fill([
            'name' => $name,
            'password' => $password,
        ]);
        $admin->forceFill(['is_admin' => true]);
        $admin->save();

        $this->info('Administrador central local configurado com sucesso.');

        return self::SUCCESS;
    }
}
