<?php

namespace App\Enums;

enum TenantStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::ACTIVE => 'Ativo',
            self::SUSPENDED => 'Suspenso',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
