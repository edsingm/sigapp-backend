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
    public function __construct(
        private readonly ReportTemplateRepository $repository,
        private readonly ReportCatalogService $catalog,
    ) {}

    /** @return Collection<int, ReportTemplate> */
    public function list(User $user): Collection
    {
        $this->ensureSystemTemplates();

        return $this->repository->listForUser($user);
    }

    public function find(User $user, int $id): ReportTemplate
    {
        $this->ensureSystemTemplates();

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
     * Garante templates de sistema no schema do tenant (idempotente).
     */
    public function ensureSystemTemplates(): void
    {
        foreach ($this->catalog->systemTemplateBlueprints() as $blueprint) {
            $definition = $this->normalizeDefinition($blueprint['definition']);
            $definition['system_key'] = $blueprint['system_key'];
            $definition['preferred_format'] = $blueprint['preferred_format'];

            $existing = $this->repository->findSystemByKey($blueprint['system_key']);
            if ($existing === null) {
                $this->repository->createSystem([
                    'name' => $blueprint['name'],
                    'scope' => 'shared',
                    'definition' => $definition,
                    'version' => 1,
                    'is_system' => true,
                ]);

                continue;
            }

            // Atualiza definition dos system templates sem sobrescrever o nome customizado se houver.
            $this->repository->update($existing, [
                'definition' => $definition,
                'name' => $blueprint['name'],
                'version' => max(1, (int) $existing->version),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public function normalizeDefinition(array $definition): array
    {
        $mode = (string) ($definition['mode'] ?? ReportCatalogService::MODE_AGGREGATE);
        if (! in_array($mode, $this->catalog->modeKeys(), true)) {
            throw ValidationException::withMessages(['mode' => ['Modo de relatório não permitido.']]);
        }

        $datasets = $this->allowlisted($definition['datasets'] ?? [], $this->catalog->datasetKeys(), 'datasets');
        $charts = $this->allowlistedOptional($definition['charts'] ?? ['table'], $this->catalog->chartKeys(), 'charts')
            ?: ['table'];

        if ($mode === ReportCatalogService::MODE_DETAIL) {
            $columns = $this->normalizeColumnsAcrossDatasets($datasets, $definition['columns'] ?? []);
            // Dimensões/métricas opcionais no detalhe (úteis se o usuário alternar para agregado depois).
            $dimensions = $this->allowlistedOptional(
                $definition['dimensions'] ?? $this->defaultDimensions($datasets),
                $this->unionKeys($datasets, fn (string $dataset): array => $this->catalog->dimensionKeysFor($dataset)),
                'dimensions',
            );
            $metrics = $this->allowlistedOptional(
                $definition['metrics'] ?? ['count'],
                $this->unionKeys($datasets, fn (string $dataset): array => $this->catalog->metricKeysFor($dataset)),
                'metrics',
            );

            $normalized = [
                'mode' => $mode,
                'datasets' => array_values(array_unique($datasets)),
                'columns' => $columns,
                'dimensions' => $dimensions,
                'metrics' => $metrics === [] ? ['count'] : $metrics,
                'charts' => $charts,
            ];
        } else {
            $allowedDimensions = $this->unionKeys(
                $datasets,
                fn (string $dataset): array => $this->catalog->dimensionKeysFor($dataset),
            );
            $allowedMetrics = $this->unionKeys(
                $datasets,
                fn (string $dataset): array => $this->catalog->metricKeysFor($dataset),
            );

            $dimensions = $this->allowlisted($definition['dimensions'] ?? [], $allowedDimensions, 'dimensions');
            $metrics = $this->allowlisted($definition['metrics'] ?? [], $allowedMetrics, 'metrics');

            // Garante que o dataset primário aceita ao menos uma dimensão/métrica escolhida.
            $primary = $datasets[0];
            if (array_intersect($dimensions, $this->catalog->dimensionKeysFor($primary)) === []) {
                throw ValidationException::withMessages([
                    'dimensions' => ['Nenhuma dimensão é válida para o dataset principal.'],
                ]);
            }
            if (array_intersect($metrics, $this->catalog->metricKeysFor($primary)) === []) {
                throw ValidationException::withMessages([
                    'metrics' => ['Nenhuma métrica é válida para o dataset principal.'],
                ]);
            }

            $normalized = [
                'mode' => ReportCatalogService::MODE_AGGREGATE,
                'datasets' => array_values(array_unique($datasets)),
                'dimensions' => array_values(array_unique($dimensions)),
                'metrics' => array_values(array_unique($metrics)),
                'charts' => $charts,
                'columns' => [],
            ];
        }

        if (isset($definition['system_key']) && is_string($definition['system_key'])) {
            $normalized['system_key'] = $definition['system_key'];
        }
        if (isset($definition['preferred_format']) && is_string($definition['preferred_format'])) {
            $normalized['preferred_format'] = $definition['preferred_format'];
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $datasets
     * @return list<string>
     */
    private function normalizeColumnsAcrossDatasets(array $datasets, mixed $requested): array
    {
        $union = $this->unionKeys(
            $datasets,
            fn (string $dataset): array => $this->catalog->columnKeysFor($dataset),
        );

        if (! is_array($requested) || $requested === []) {
            // Default: colunas do primeiro dataset (até 8).
            return array_slice($this->catalog->columnKeysFor($datasets[0]), 0, 8);
        }

        return $this->allowlisted($requested, $union, 'columns');
    }

    /**
     * @param  list<string>  $datasets
     * @return list<string>
     */
    private function defaultDimensions(array $datasets): array
    {
        $dims = $this->catalog->dimensionKeysFor($datasets[0]);

        return $dims === [] ? ['status'] : [array_values($dims)[0]];
    }

    /**
     * @param  list<string>  $datasets
     * @param  callable(string): list<string>  $resolver
     * @return list<string>
     */
    private function unionKeys(array $datasets, callable $resolver): array
    {
        $keys = [];
        foreach ($datasets as $dataset) {
            foreach ($resolver($dataset) as $key) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
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

        return array_values(array_unique($values));
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function allowlistedOptional(mixed $values, array $allowed, string $field): array
    {
        if (! is_array($values) || $values === []) {
            return [];
        }

        $values = array_values(array_filter($values, 'is_string'));
        $invalid = array_values(array_diff($values, $allowed));
        if ($invalid !== []) {
            throw ValidationException::withMessages([$field => ['Há itens não permitidos no catálogo.']]);
        }

        return array_values(array_unique($values));
    }
}
