<?php

namespace App\Enums;

enum LegalizacaoStatus: string
{
    case PLANEJADO = 'planejado';
    case EM_ANDAMENTO = 'em_andamento';
    case CONCLUIDO = 'concluido';
    case CANCELADO = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::PLANEJADO => 'Planejado',
            self::EM_ANDAMENTO => 'Em andamento',
            self::CONCLUIDO => 'Concluído',
            self::CANCELADO => 'Cancelado',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}