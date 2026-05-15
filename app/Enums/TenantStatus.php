<?php

namespace App\Enums;

enum TenantStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';
    case SETUP_FAILED = 'setup_failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::ACTIVE => 'Ativo',
            self::SUSPENDED => 'Suspenso',
            self::CANCELLED => 'Cancelado',
            self::SETUP_FAILED => 'Falha na configuração',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
