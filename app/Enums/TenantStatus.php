<?php

namespace App\Enums;

enum TenantStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';
    case SETUP_FAILED = 'setup_failed';
    case UNDER_REVIEW = 'under_review';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::ACTIVE => 'Ativo',
            self::SUSPENDED => 'Suspenso',
            self::CANCELLED => 'Cancelado',
            self::SETUP_FAILED => 'Falha na configuração',
            self::UNDER_REVIEW => 'Em Revisão',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isUnderReview(): bool
    {
        return $this === self::UNDER_REVIEW;
    }

    /**
     * Status em que o usuário ainda pode autenticar para regularizar cobrança
     * (portal, dunning, histórico) mesmo sem acesso aos módulos de negócio.
     */
    public function allowsLogin(): bool
    {
        return match ($this) {
            self::ACTIVE, self::SUSPENDED, self::UNDER_REVIEW => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function loginEligibleValues(): array
    {
        return array_values(array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), static fn (self $status): bool => $status->allowsLogin()),
        ));
    }
}
