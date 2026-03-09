<?php

namespace App\Enums\Common;

use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\Documento;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\Produto;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Regional;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\TerrenoStatus;
use App\Models\Tenant\Viabilidade;

enum ModulesEnum: string
{
    case ADMIN = 'admin';
    case CONFIGURATIONS = 'configurations';
    case PROSPECTION = 'prospection';
    case BROKERS = 'brokers';
    case DATA = 'data';
    case DASHBOARD = 'dashboard';
    case LEGAL = 'legal';
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
            self::LEGAL => language()->t('LEGAL'),
            self::PROJECTS => language()->t('PROJECTS'),
            self::REPORTS => language()->t('REPORTS'),
            self::VIABILITY => language()->t('VIABILITY')
        };
    }

    /**
     * Sub-modules (resources) within this module.
     * Empty array means the module has no sub-modules and is accessed at the module level.
     *
     * To add resources to a module, declare them here. The permission seeder will
     * automatically generate permissions for them in {module}.{resource}.{level} format.
     *
     * @return array<int, string>
     */
    public function resources(): array
    {
        return match ($this) {
            self::PROSPECTION => ['terrains','maps'],
            default        => [],
        };
    }

    public function hasResources(): bool
    {
        return !empty($this->resources());
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
                Terreno::class => 'terrains',
            ],
            self::BROKERS => [
                CorretorExterno::class => null,
            ],
            self::DATA => [
                Regional::class       => null,
                Produto::class        => null,
                Proprietario::class   => null,
                TerrenoProduto::class => null,
                TerrenoStatus::class  => null,
                Documento::class      => null,
            ],
            self::LEGAL => [
                Legalizacao::class      => null,
                LegalizacaoEtapa::class => null,
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
