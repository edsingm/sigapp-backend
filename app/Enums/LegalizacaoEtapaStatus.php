<?php

namespace App\Enums;

enum LegalizacaoEtapaStatus: string
{
    case PENDENTE = 'pendente';
    case EM_ANDAMENTO = 'em_andamento';
    case CONCLUIDA = 'concluida';
    case BLOQUEADA = 'bloqueada';
    case ATRASADA = 'atrasada';

    public function label(): string
    {
        return match ($this) {
            self::PENDENTE => 'Pendente',
            self::EM_ANDAMENTO => 'Em andamento',
            self::CONCLUIDA => 'Concluída',
            self::BLOQUEADA => 'Bloqueada',
            self::ATRASADA => 'Atrasada',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}