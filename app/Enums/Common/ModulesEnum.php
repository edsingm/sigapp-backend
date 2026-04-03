<?php

namespace App\Enums\Common;

use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Contrato;
use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\Documento;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Produto;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Regional;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\Viabilidade;

enum ModulesEnum: string
{
    case ADMIN = 'admin';
    case CONFIGURATIONS = 'configurations';
    case PROSPECTION = 'prospection';
    case BROKERS = 'brokers';
    case DATA = 'data';
    case DASHBOARD = 'dashboard';
    case COMMITTEE = 'committee';
    case LEGAL = 'legal';
    case NEGOTIATION = 'negotiation';
    case PROJECTS = 'projects';
    case REPORTS = 'reports';
    case VIABILITY = 'viability';
    case AI = 'ai';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => language()->t('ADMIN'),
            self::CONFIGURATIONS => language()->t('CONFIGURATIONS'),
            self::PROSPECTION => language()->t('PROSPECTION'),
            self::BROKERS => language()->t('BROKERS'),
            self::DATA => language()->t('DATA'),
            self::DASHBOARD => language()->t('DASHBOARD'),
            self::COMMITTEE => language()->t('COMMITTEE'),
            self::LEGAL => language()->t('LEGAL'),
            self::NEGOTIATION => language()->t('NEGOTIATION'),
            self::PROJECTS => language()->t('PROJECTS'),
            self::REPORTS => language()->t('REPORTS'),
            self::VIABILITY => language()->t('VIABILITY'),
            self::AI => language()->t('AI')
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::DASHBOARD      => 10,
            self::PROSPECTION    => 20,
            self::BROKERS        => 30,
            self::VIABILITY      => 40,
            self::COMMITTEE      => 50,
            self::NEGOTIATION    => 60,
            self::LEGAL          => 70,
            self::PROJECTS       => 80,
            self::CONFIGURATIONS => 90,
            self::DATA           => 100,
            self::REPORTS        => 110,
            self::ADMIN          => 120,
            self::AI             => 130
        };
    }

    public function sector(): SectorsEnum
    {
        return match ($this) {
            self::DASHBOARD      => SectorsEnum::PRINCIPAL,
            self::PROSPECTION,
            self::BROKERS,
            self::VIABILITY,
            self::COMMITTEE,
            self::NEGOTIATION,
            self::LEGAL,
            self::PROJECTS,
            self::AI             => SectorsEnum::OPERATION,
            self::CONFIGURATIONS,
            self::DATA           => SectorsEnum::CONFIGURATION,
            self::REPORTS        => SectorsEnum::INTELLIGENCE,
            self::ADMIN          => SectorsEnum::ADMINISTRATION
        };
    }

    /**
     * Sub-módulos (recursos) dentro deste módulo.
     * Array vazio significa que o módulo não possui sub-módulos e é acessado no nível do módulo.
     *
     * Para adicionar recursos a um módulo, declare-os aqui. O seeder de permissões irá
     * gerar automaticamente permissões para eles no formato {módulo}.{recurso}.{nível}.
     *
     * @return array<int, SubmodulesEnum>
     */
    public function submodules(): array
    {
        return match ($this) {
            self::PROSPECTION => [SubmodulesEnum::TERRAINS, SubmodulesEnum::MAPS],
            default           => [],
        };
    }

    public function hasSubmodules(): bool
    {
        return !empty($this->submodules());
    }

    /**
     * Mapeia as classes de modelo que pertencem a este módulo.
     * Chave = nome completo da classe do modelo.
     * Valor = nome do recurso dentro do módulo, ou null quando o módulo não possui recursos.
     *
     * Ao adicionar um novo módulo, declare seus modelos aqui e o TenantPolicy
     * os reconhecerá automaticamente — não são necessárias alterações na política.
     *
     * @return array<class-string, string|null>
     */
    public function models(): array
    {
        return match ($this) {
            self::PROSPECTION => [
                Terreno::class => SubmodulesEnum::TERRAINS->value,
            ],
            self::BROKERS => [
                CorretorExterno::class => null,
            ],
            self::DATA => [
                Regional::class => null,
                Produto::class => null,
                Proprietario::class => null,
                TerrenoProduto::class => null,
                Documento::class => null,
            ],
            self::LEGAL => [
                Legalizacao::class => null,
                LegalizacaoEtapa::class => null,
            ],
            self::COMMITTEE => [
                ComiteRevisao::class => null,
            ],
            self::NEGOTIATION => [
                Negociacao::class => null,
                Contrato::class => null,
            ],
            self::PROJECTS => [
                Projeto::class => null,
            ],
            self::VIABILITY => [
                Viabilidade::class => null,
            ],
            self::AI => [],
            default => [],
        };
    }

    /**
     * Mapeamento simples de [ModelClass => 'module.resource' | 'module'] para todos os casos.
     * Usado pelo TenantPolicy para resolver a string de permissão para um modelo dado.
     *
     * @return array<class-string, string>
     */
    public static function modelMap(): array
    {
        static $map = null;

        if ($map === null) {
            $map = [];
            foreach (self::cases() as $case) {
                foreach ($case->models() as $modelClass => $resource) {
                    $map[$modelClass] = $resource !== null
                        ? "{$case->value}.{$resource}"
                        : $case->value;
                }
            }
        }

        return $map;
    }
}
