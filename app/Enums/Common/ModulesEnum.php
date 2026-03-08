<?php

namespace App\Enums\Common;

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
            self::ADMIN => 'Administração',
            self::CONFIGURATIONS => 'Configurações',
            self::PROSPECTION => 'Prospecção',
            self::BROKERS => 'Corretores',
            self::DATA => 'Dados',
            self::DASHBOARD => 'Dashboard',
            self::LEGAL => 'Legalizações',
            self::PROJECTS => 'Projetos',
            self::REPORTS => 'Relatórios',
            self::VIABILITY => 'Viabilidade'
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
}
