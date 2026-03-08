<?php

namespace App\Policies\Tenant;

use App\Enums\Common\ModulesEnum;
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
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;

/**
 * Single policy for all tenant models.
 *
 * Maps each model class to its module (dot-notation) and translates standard
 * CRUD methods to permission levels following the seeder convention:
 *
 *   viewAny / view            → {module}.viewer
 *   create  / update          → {module}.editor
 *   delete  / restore         → {module}.manager
 *   any other method (export,
 *     ativar, approve, etc.)  → {module}.manager
 *
 * Admin and super_admin roles bypass all checks via before().
 *
 * Registered in AppServiceProvider via Gate::policy(ModelClass::class, TenantPolicy::class).
 */
class TenantPolicy
{
    /**
     * Maps model class → permission module string (dot-notation).
     * Modules with resources use "{module}.{resource}" format.
     */
    private const MODEL_MAP = [
        // prospection module (has resources)
        Terreno::class          => 'prospection.terrains',

        // brokers module
        CorretorExterno::class  => ModulesEnum::BROKERS->value,

        // data module
        Regional::class         => ModulesEnum::DATA->value,
        Produto::class          => ModulesEnum::DATA->value,
        Proprietario::class     => ModulesEnum::DATA->value,
        TerrenoProduto::class   => ModulesEnum::DATA->value,
        TerrenoStatus::class    => ModulesEnum::DATA->value,
        Documento::class        => ModulesEnum::DATA->value,

        // legal module
        Legalizacao::class      => ModulesEnum::LEGAL->value,
        LegalizacaoEtapa::class => ModulesEnum::LEGAL->value,

        // projects module
        Projeto::class          => ModulesEnum::PROJECTS->value,

        // viability module
        Viabilidade::class      => ModulesEnum::VIABILITY->value,
    ];

    /**
     * Admin and super_admin bypass all permission checks.
     */
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    // ── viewer level ──────────────────────────────────────────────────────────

    public function viewAny(User $user, string $modelClass): bool
    {
        return $user->can($this->permission($modelClass, 'viewer'));
    }

    public function view(User $user, mixed $model): bool
    {
        return $user->can($this->permission(get_class($model), 'viewer'));
    }

    // ── editor level ──────────────────────────────────────────────────────────

    public function create(User $user, string $modelClass): bool
    {
        return $user->can($this->permission($modelClass, 'editor'));
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->can($this->permission(get_class($model), 'editor'));
    }

    // ── manager level ─────────────────────────────────────────────────────────

    public function delete(User $user, mixed $model): bool
    {
        return $user->can($this->permission(get_class($model), 'manager'));
    }

    public function restore(User $user, mixed $model): bool
    {
        return $user->can($this->permission(get_class($model), 'manager'));
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return $user->can($this->permission(get_class($model), 'manager'));
    }

    /**
     * Catch-all for extra actions (export, ativar, approve, cancel, syncGantt, etc.).
     * All non-standard actions require manager level.
     *
     * @param  array{0: User, 1: mixed}  $arguments
     */
    public function __call(string $method, array $arguments): bool
    {
        [$user, $model] = $arguments;
        $class = is_object($model) ? get_class($model) : (string) $model;

        return $user->can($this->permission($class, 'manager'));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function permission(string $modelClass, string $level): string
    {
        $module = self::MODEL_MAP[$modelClass] ?? null;

        if (!$module) {
            return "unknown.{$level}";
        }

        return "{$module}.{$level}";
    }
}
