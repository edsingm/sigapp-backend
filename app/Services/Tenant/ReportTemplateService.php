<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\ReportTemplate;
use App\Models\Tenant\User;
use App\Repositories\Tenant\ReportTemplateRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ReportTemplateService
{
    /** @var list<string> */
    private const DATASETS = ['terrenos', 'viabilidades', 'comites', 'legalizacoes'];

    /** @var list<string> */
    private const DIMENSIONS = ['status', 'workflow_status_code', 'estado', 'created_at'];

    /** @var list<string> */
    private const METRICS = ['count', 'sum_valor'];

    /** @var list<string> */
    private const CHARTS = ['table', 'bar', 'line'];

    public function __construct(private readonly ReportTemplateRepository $repository) {}

    /** @return Collection<int, ReportTemplate> */
    public function list(User $user): Collection
    {
        return $this->repository->listForUser($user);
    }

    public function find(User $user, int $id): ReportTemplate
    {
        return $this->repository->findForUser($user, $id);
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): ReportTemplate
    {
        return $this->repository->create($user, [
            'name' => $data['name'],
            'scope' => $data['scope'] ?? 'private',
            'definition' => $this->normalizeDefinition($data['definition']),
            'version' => 1,
            'is_system' => false,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, int $id, array $data): ReportTemplate
    {
        $template = $this->find($user, $id);
        if ($template->is_system || (int) $template->owner_id !== (int) $user->id) {
            throw ValidationException::withMessages(['template' => ['Este template não pode ser editado por este usuário.']]);
        }

        $updates = [];
        if (array_key_exists('name', $data)) {
            $updates['name'] = $data['name'];
        }
        if (array_key_exists('scope', $data)) {
            $updates['scope'] = $data['scope'];
        }
        if (array_key_exists('definition', $data)) {
            $updates['definition'] = $this->normalizeDefinition($data['definition']);
        }
        if ($updates !== []) {
            $updates['version'] = $template->version + 1;
        }

        return $this->repository->update($template, $updates);
    }

    public function delete(User $user, int $id): void
    {
        $template = $this->find($user, $id);
        if ($template->is_system || (int) $template->owner_id !== (int) $user->id) {
            throw ValidationException::withMessages(['template' => ['Este template não pode ser excluído por este usuário.']]);
        }
        $template->delete();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public function normalizeDefinition(array $definition): array
    {
        $datasets = $this->allowlisted($definition['datasets'] ?? [], self::DATASETS, 'datasets');
        $dimensions = $this->allowlisted($definition['dimensions'] ?? [], self::DIMENSIONS, 'dimensions');
        $metrics = $this->allowlisted($definition['metrics'] ?? [], self::METRICS, 'metrics');
        $charts = $this->allowlisted($definition['charts'] ?? ['table'], self::CHARTS, 'charts');

        return [
            'datasets' => array_values(array_unique($datasets)),
            'dimensions' => array_values(array_unique($dimensions)),
            'metrics' => array_values(array_unique($metrics)),
            'charts' => array_values(array_unique($charts)),
        ];
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function allowlisted(mixed $values, array $allowed, string $field): array
    {
        if (! is_array($values) || $values === []) {
            throw ValidationException::withMessages([$field => ['Informe ao menos um item permitido.']]);
        }

        $values = array_values(array_filter($values, 'is_string'));
        $invalid = array_values(array_diff($values, $allowed));
        if ($invalid !== []) {
            throw ValidationException::withMessages([$field => ['Há itens não permitidos no catálogo.']]);
        }

        return $values;
    }
}
