<?php

namespace App\Enums;

enum ProjetoStatus: string
{
    case EM_VIABILIDADE = 'em_viabilidade';
    case EM_LEGALIZACAO = 'em_legalizacao';
    case FINALIZADO = 'finalizado';
    case PRONTO_PARA_REGISTRO = 'pronto_para_registro';
    case CANCELADO = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::EM_VIABILIDADE => 'Em viabilidade',
            self::EM_LEGALIZACAO => 'Em legalização',
            self::FINALIZADO => 'Finalizado',
            self::PRONTO_PARA_REGISTRO => 'Pronto para registro',
            self::CANCELADO => 'Cancelado',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
