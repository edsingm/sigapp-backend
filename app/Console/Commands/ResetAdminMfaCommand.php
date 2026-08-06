<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Auth\AdminMfaService;
use Illuminate\Console\Command;

class ResetAdminMfaCommand extends Command
{
    protected $signature = 'admin:mfa-reset
                            {email : E-mail do administrador central}
                            {--operator= : Identificador do operador que autorizou o reset}
                            {--reason= : Motivo operacional do reset}';

    protected $description = 'Revoga o MFA de um administrador central e exige novo cadastro no próximo login';

    public function handle(AdminMfaService $service): int
    {
        $email = $this->stringValue($this->argument('email'));
        $operator = $this->stringValue($this->option('operator'));
        $reason = $this->stringValue($this->option('reason'));

        if ($operator === '' || $reason === '') {
            $this->error('Informe --operator e --reason para registrar o reset no audit log.');

            return self::INVALID;
        }

        if (! $this->confirm("Resetar o MFA de {$email}?")) {
            $this->comment('Operação cancelada.');

            return self::SUCCESS;
        }

        $service->reset($email, $operator, $reason);
        $this->info('MFA resetado. O administrador deverá cadastrar um novo autenticador.');

        return self::SUCCESS;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
