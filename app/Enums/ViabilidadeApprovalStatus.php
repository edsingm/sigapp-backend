<?php

declare(strict_types=1);

namespace App\Enums;

enum ViabilidadeApprovalStatus: string
{
    case Pendente = 'pendente';
    case EmAprovacao = 'em_aprovacao';
    case Aprovada = 'aprovada';
    case Rejeitada = 'rejeitada';
    case Revogada = 'revogada';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::EmAprovacao => 'Em aprovação',
            self::Aprovada => 'Aprovada',
            self::Rejeitada => 'Rejeitada',
            self::Revogada => 'Revogada',
        };
    }

    public function isMutable(): bool
    {
        return $this === self::Pendente;
    }

    public function isLocked(): bool
    {
        return ! $this->isMutable();
    }

    public function canSubmit(): bool
    {
        return $this === self::Pendente;
    }

    public function canDecide(): bool
    {
        return $this === self::EmAprovacao;
    }

    public function canRevoke(): bool
    {
        return $this === self::Aprovada;
    }

    /**
     * @return list<string>
     */
    public function allowedActions(): array
    {
        return match ($this) {
            self::Pendente => ['edit', 'recalculate', 'submit', 'duplicate', 'delete'],
            self::EmAprovacao => ['approve', 'reject', 'duplicate'],
            self::Aprovada => ['revoke', 'duplicate', 'recalculate_as_new_version'],
            self::Rejeitada, self::Revogada => ['duplicate', 'recalculate_as_new_version'],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromMixed(mixed $value, ?string $legacyStatus = null): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $normalized = strtolower(trim($value));

            // Aliases legados / inglês ainda usados em endpoints e histórico antigo.
            $alias = match ($normalized) {
                'reprovada', 'rejected', 'reject' => self::Rejeitada,
                'approved', 'approve' => self::Aprovada,
                'pending' => self::Pendente,
                'revoked' => self::Revogada,
                default => null,
            };
            if ($alias instanceof self) {
                return $alias;
            }

            $parsed = self::tryFrom($normalized);
            if ($parsed instanceof self) {
                return $parsed;
            }
        }

        if ($legacyStatus === 'ativo') {
            return self::Aprovada;
        }

        return self::Pendente;
    }
}
