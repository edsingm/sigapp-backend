<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\ReportTemplate;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReportTemplateRepository
{
    /** @return Collection<int, ReportTemplate> */
    public function listForUser(User $user): Collection
    {
        $query = ReportTemplate::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('owner_id', $user->id)
                ->orWhere('scope', 'shared')
                ->orWhere('is_system', true))
            ->with('owner:id,name')
            ->orderByDesc('is_system')
            ->orderBy('name');

        /** @var Collection<int, ReportTemplate> $templates */
        $templates = $query->get();

        return $templates;
    }

    public function findForUser(User $user, int $id): ReportTemplate
    {
        return ReportTemplate::query()
            ->whereKey($id)
            ->where(fn (Builder $query): Builder => $query
                ->where('owner_id', $user->id)
                ->orWhere('scope', 'shared')
                ->orWhere('is_system', true))
            ->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): ReportTemplate
    {
        return ReportTemplate::query()->create([...$data, 'owner_id' => $user->id]);
    }

    /** @param array<string, mixed> $data */
    public function createSystem(array $data): ReportTemplate
    {
        return ReportTemplate::query()->create([
            ...$data,
            'owner_id' => null,
            'is_system' => true,
            'scope' => $data['scope'] ?? 'shared',
        ]);
    }

    public function findSystemByKey(string $systemKey): ?ReportTemplate
    {
        /** @var Collection<int, ReportTemplate> $candidates */
        $candidates = ReportTemplate::query()
            ->where('is_system', true)
            ->get();

        foreach ($candidates as $template) {
            $key = $template->definition['system_key'] ?? null;
            if (is_string($key) && $key === $systemKey) {
                return $template;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    public function update(ReportTemplate $template, array $data): ReportTemplate
    {
        $template->update($data);

        return $template->fresh('owner') ?? $template;
    }
}
