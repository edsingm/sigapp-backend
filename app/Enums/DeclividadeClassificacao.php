<?php

namespace App\Enums;

enum DeclividadeClassificacao: string
{
    case Ideal = 'ideal';
    case Boa = 'boa';
    case Desafiadora = 'desafiadora';
    case LimiteLegal = 'limite_legal';
    case AcimaDoLimite = 'acima_do_limite';

    public function label(): string
    {
        return match ($this) {
            self::Ideal => 'Ideal / Ótima',
            self::Boa => 'Boa / Aceitável',
            self::Desafiadora => 'Desafiadora',
            self::LimiteLegal => 'Limite legal',
            self::AcimaDoLimite => 'Acima do limite legal',
        };
    }

    public function avaliacao(): string
    {
        return match ($this) {
            self::Ideal => 'Terreno praticamente plano ou com suave inclinação',
            self::Boa => 'Ainda viável, exige algum movimento de terra',
            self::Desafiadora => 'Possível, mas exige projeto cuidadoso e contenções',
            self::LimiteLegal => 'Permitido pela Lei 6.766/79 (com restrições e aprovação técnica)',
            self::AcimaDoLimite => 'Acima do limite legal de 30% - não recomendado para edificação',
        };
    }

    public function impactoNoCusto(): string
    {
        return match ($this) {
            self::Ideal => 'Mais baixo',
            self::Boa => 'Médio',
            self::Desafiadora => 'Alto',
            self::LimiteLegal => 'Muito alto',
            self::AcimaDoLimite => 'Inviável',
        };
    }

    public static function fromSlope(float $slopePercent): self
    {
        return match (true) {
            $slopePercent <= 8.0 => self::Ideal,
            $slopePercent <= 15.0 => self::Boa,
            $slopePercent <= 25.0 => self::Desafiadora,
            $slopePercent <= 30.0 => self::LimiteLegal,
            default => self::AcimaDoLimite,
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
