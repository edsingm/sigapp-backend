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
            self::VIABILITY => language()->t('VIABILITY')
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
            self::PROJECTS       => SectorsEnum::OPERATION,
            self::CONFIGURATIONS,
            self::DATA           => SectorsEnum::CONFIGURATION,
            self::REPORTS        => SectorsEnum::INTELLIGENCE,
            self::ADMIN          => SectorsEnum::ADMINISTRATION,
        };
    }

    /**
     * Sub-modules (resources) within this module.
     * Empty array means the module has no sub-modules and is accessed at the module level.
     *
     * To add resources to a module, declare them here. The permission seeder will
     * automatically generate permissions for them in {module}.{resource}.{level} format.
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
     * Maps model classes that belong to this module.
     * Key = fully-qualified model class name.
     * Value = resource name within the module, or null when the module has no resources.
     *
     * When you add a new module, declare its models here and TenantPolicy
     * will automatically pick them up — no changes needed in the policy.
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
                Regional::class       => null,
                Produto::class        => null,
                Proprietario::class   => null,
                TerrenoProduto::class => null,
                Documento::class      => null,
            ],
            self::LEGAL => [
                Legalizacao::class      => null,
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
            default => [],
        };
    }

    /**
     * Flat map of [ModelClass => 'module.resource' | 'module'] for all cases.
     * Used by TenantPolicy to resolve the permission string for a given model.
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
