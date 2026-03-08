<?php

namespace App\Enums;

enum Module: string
{
    case TERRENOS            = 'terrenos';
    case DOCUMENTOS          = 'documentos';
    case PRODUTOS            = 'produtos';
    case PROPRIETARIOS       = 'proprietarios';
    case REGIONAIS           = 'regionais';
    case CORRETORES_EXTERNOS = 'corretores_externos';
    case VIABILIDADES        = 'viabilidades';
    case PROJETOS            = 'projetos';
    case TERRENO_PRODUTOS    = 'terreno_produtos';
    case TERRENO_STATUS      = 'terreno_status';
    case LEGALIZACOES        = 'legalizacoes';
    case LEGALIZACAO_ETAPAS  = 'legalizacao_etapas';

    public function label(): string
    {
        return match ($this) {
            self::TERRENOS            => 'Terrenos',
            self::DOCUMENTOS          => 'Documentos',
            self::PRODUTOS            => 'Produtos',
            self::PROPRIETARIOS       => 'Proprietários',
            self::REGIONAIS           => 'Regionais',
            self::CORRETORES_EXTERNOS => 'Corretores Externos',
            self::VIABILIDADES        => 'Viabilidades',
            self::PROJETOS            => 'Projetos',
            self::TERRENO_PRODUTOS    => 'Terreno Produtos',
            self::TERRENO_STATUS      => 'Terreno Status',
            self::LEGALIZACOES        => 'Legalizações',
            self::LEGALIZACAO_ETAPAS  => 'Etapas de Legalização',
        };
    }
}
