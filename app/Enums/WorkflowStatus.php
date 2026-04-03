<?php

namespace App\Enums;

enum WorkflowStatus: string
{
    case EM_ANALISE = 'em_analise';
    case AGUARDANDO_VIABILIDADE = 'aguardando_viabilidade';
    case VIABILIDADE_APROVADA = 'viabilidade_aprovada';
    case AGUARDANDO_COMITE = 'aguardando_comite';
    case NEGOCIACAO_MINUTA = 'negociacao_minuta';
    case CONTRATO_ASSINADO = 'contrato_assinado';
    case LEGALIZANDO = 'legalizando';
    case LEGALIZADO_FINALIZADO = 'legalizado_finalizado';
    case DESCARTADO = 'descartado';
    case ARQUIVADO = 'arquivado';

    public function label(): string
    {
        return match ($this) {
            self::EM_ANALISE => 'Em análise',
            self::AGUARDANDO_VIABILIDADE => 'Aguardando viabilidade',
            self::VIABILIDADE_APROVADA => 'Viabilidade aprovada',
            self::AGUARDANDO_COMITE => 'Aguardando comitê',
            self::NEGOCIACAO_MINUTA => 'Negociação/Minuta',
            self::CONTRATO_ASSINADO => 'Contrato assinado',
            self::LEGALIZANDO => 'Legalizando',
            self::LEGALIZADO_FINALIZADO => 'Legalizado/Finalizado',
            self::DESCARTADO => 'Descartado',
            self::ARQUIVADO => 'Arquivado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EM_ANALISE => '#0EA5E9',
            self::AGUARDANDO_VIABILIDADE => '#F59E0B',
            self::VIABILIDADE_APROVADA => '#10B981',
            self::AGUARDANDO_COMITE => '#06B6D4',
            self::NEGOCIACAO_MINUTA => '#8B5CF6',
            self::CONTRATO_ASSINADO => '#047857',
            self::LEGALIZANDO => '#65A30D',
            self::LEGALIZADO_FINALIZADO => '#0F766E',
            self::DESCARTADO => '#E11D48',
            self::ARQUIVADO => '#475569',
        };
    }

    public function stage(): string
    {
        return match ($this) {
            self::EM_ANALISE => 'captacao',
            self::AGUARDANDO_VIABILIDADE,
            self::VIABILIDADE_APROVADA => 'viabilidade',
            self::AGUARDANDO_COMITE => 'comite',
            self::NEGOCIACAO_MINUTA,
            self::CONTRATO_ASSINADO => 'negociacao_contrato',
            self::LEGALIZANDO => 'legalizacao',
            self::LEGALIZADO_FINALIZADO,
            self::DESCARTADO,
            self::ARQUIVADO => 'encerramento',
        };
    }

    /** Status(es) que representam negociação ativa. */
    public static function negotiationActive(): array
    {
        return [self::NEGOCIACAO_MINUTA->value];
    }

    /** Status(es) que representam contrato assinado e etapas posteriores. */
    public static function signedAndLater(): array
    {
        return [
            self::CONTRATO_ASSINADO->value,
            self::LEGALIZANDO->value,
            self::LEGALIZADO_FINALIZADO->value,
        ];
    }

    /** Status(es) de encerramento (descarte ou arquivo). */
    public static function closure(): array
    {
        return [self::DESCARTADO->value, self::ARQUIVADO->value];
    }

    /** Retorna todos os valores de string (útil para validações Rule::in). */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
