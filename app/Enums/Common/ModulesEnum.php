<?php

namespace App\Enums\Common;

enum ModulesEnum: string
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

    /**
     * Sub-modules (resources) within this module.
     * Empty array means the module has no sub-modules and is accessed at the module level.
     *
     * To add sub-modules to a module, declare them here.
     * The AclPermissionCatalogService will automatically generate permissions for them.
     *
     * @return array<int, string>
     */
    public function subModules(): array
    {
        return match ($this) {
            self::TERRENOS => ['predio', 'casa', 'comercial'],
            default        => [],
        };
    }

    public function hasSubModules(): bool
    {
        return !empty($this->subModules());
    }

    /**
     * Extra actions exclusive to the MANAGER level for this module.
     * These are generated as module-level permissions (not sub-module level).
     *
     * Base actions (view_any, view, create, update, delete, restore) are always generated
     * automatically by AclPermissionCatalogService and do not need to be listed here.
     *
     * @return array<int, string>
     */
    public function extraActions(): array
    {
        return match ($this) {
            self::TERRENOS           => ['export'],
            self::VIABILIDADES       => ['request_approval', 'approve', 'activate', 'duplicate', 'compare', 'generate_dre', 'recalculate', 'export'],
            self::PROJETOS           => ['cancel', 'mark_ready'],
            self::LEGALIZACOES       => ['sync_gantt', 'recalculate_progress'],
            self::LEGALIZACAO_ETAPAS => ['reorder'],
            default                  => [],
        };
    }
}
