<?php

declare(strict_types=1);

namespace App\Enums;

enum PerfilFinanciamento: string
{
    /**
     * Valor legado. Novos estudos usam APOIO_PRODUCAO, mas os snapshots e
     * registros históricos em `cef` precisam continuar reproduzíveis.
     */
    case CEF = 'cef';
    case PROPRIO = 'proprio';
    case APOIO_PRODUCAO = 'apoio_producao';
    case PLANO_EMPRESARIO = 'plano_empresario';
    case ALOCACAO_RECURSOS = 'alocacao_recursos';

    public function label(): string
    {
        return match ($this) {
            self::CEF => 'CEF (legado — Apoio à Produção)',
            self::PROPRIO => 'Financiamento Próprio',
            self::APOIO_PRODUCAO => 'Apoio à Produção',
            self::PLANO_EMPRESARIO => 'Plano Empresário',
            self::ALOCACAO_RECURSOS => 'Alocação de Recursos',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CEF, self::APOIO_PRODUCAO => 'Recebimentos dos adquirentes proporcionais às vendas e à evolução da obra, com financiamento PJ opcional.',
            self::PROPRIO => 'Recebimentos mensais conforme a tabela comercial, sem recursos bancários.',
            self::PLANO_EMPRESARIO => 'Pagamentos dos clientes durante a obra, financiamento PJ por medição e repasse PF na entrega.',
            self::ALOCACAO_RECURSOS => 'Sem financiamento bancário da obra; o repasse PF das vendas ocorre na entrega ou após ela.',
        };
    }

    public function isCef(): bool
    {
        return $this->isApoioProducao();
    }

    public function isProprio(): bool
    {
        return $this === self::PROPRIO;
    }

    public function isApoioProducao(): bool
    {
        return $this === self::CEF || $this === self::APOIO_PRODUCAO;
    }

    public function isPlanoEmpresario(): bool
    {
        return $this === self::PLANO_EMPRESARIO;
    }

    public function isAlocacaoRecursos(): bool
    {
        return $this === self::ALOCACAO_RECURSOS;
    }

    public function permiteFinanciamentoPj(): bool
    {
        return $this->isApoioProducao() || $this->isPlanoEmpresario();
    }

    /**
     * Perfis de premissas tentados, em ordem, quando o tenant ainda não criou
     * um conjunto específico para o novo modelo.
     *
     * @return list<string>
     */
    public function perfisPremissas(): array
    {
        return match ($this) {
            self::CEF => [self::CEF->value],
            self::PROPRIO => [self::PROPRIO->value],
            self::APOIO_PRODUCAO => [self::APOIO_PRODUCAO->value, self::CEF->value],
            self::PLANO_EMPRESARIO => [self::PLANO_EMPRESARIO->value, self::CEF->value],
            self::ALOCACAO_RECURSOS => [self::ALOCACAO_RECURSOS->value, self::PROPRIO->value],
        };
    }

    /** @return list<self> */
    public static function selectableCases(): array
    {
        return [
            self::PROPRIO,
            self::APOIO_PRODUCAO,
            self::PLANO_EMPRESARIO,
            self::ALOCACAO_RECURSOS,
        ];
    }

    /** @return list<array{value: string, label: string, description: string, permite_financiamento_pj: bool}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $perfil): array => [
                'value' => $perfil->value,
                'label' => $perfil->label(),
                'description' => $perfil->description(),
                'permite_financiamento_pj' => $perfil->permiteFinanciamentoPj(),
            ],
            self::selectableCases(),
        );
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
