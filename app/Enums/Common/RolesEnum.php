<?php

namespace App\Enums\Common;

enum RolesEnum: string
{
    case ADMIN      = 'ADMIN';
    case DIRECTOR   = 'DIRECTOR';
    case MANAGER    = 'MANAGER';
    case SUPERVISOR = 'SUPERVISOR';
    case ANALYST    = 'ANALYST';
    case USER       = 'USER';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN      => 'Administrador',
            self::DIRECTOR   => 'Diretor',
            self::MANAGER    => 'Gerente',
            self::SUPERVISOR => 'Supervisor',
            self::ANALYST    => 'Analista',
            self::USER       => 'Usuário',
        };
    }
}
