<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use App\Models\Tenant\ViabilidadeScenario;
use App\Repositories\Tenant\ViabilidadeRepository;
use App\Repositories\Tenant\ViabilidadeScenarioRepository;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class ViabilidadeScenarioService
{
    private const SCENARIO_TYPES = ['base', 'optimistic', 'conservative', 'custom'];

    private const OVERRIDE_FIELDS = [
        ...Viabilidade::CAMPOS_FINANCEIROS,
        'prazo_obra',
        'prazo_lancamento',
        'prazo_incorporacao',
        'data_lancamento',
        'perfil_financiamento',
    ];

    public function __construct(
        private readonly ViabilidadeScenarioRepository $repository,
        private readonly ViabilidadeRepository $viabilidadeRepository,
        private readonly ViabilidadeService $viabilidadeService,
    ) {}

    /**
     * @return array<int, ViabilidadeScenario>
     */
    public function list(int $viabilidadeId): array
    {
        return $this->repository->listForViabilidade($viabilidadeId)->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $viabilidadeId, array $data, User $actor): ViabilidadeScenario
    {
        $this->viabilidadeRepository->findOrFail($viabilidadeId);
        $scenarioType = (string) ($data['scenario_type'] ?? 'custom');
        if (! in_array($scenarioType, self::SCENARIO_TYPES, true)) {
            throw new InvalidArgumentException('Tipo de cenário inválido.');
        }

        $overrides = $this->validatedOverrides($data['premises'] ?? []);

        return $this->repository->create([
            'viabilidade_id' => $viabilidadeId,
            'name' => $data['name'],
            'scenario_type' => $scenarioType,
            'status' => 'draft',
            'premises_snapshot' => [
                'base_viabilidade_id' => $viabilidadeId,
                'overrides' => $overrides,
            ],
            'input_hash' => hash('sha256', json_encode($overrides, JSON_THROW_ON_ERROR)),
            'created_by' => $actor->id,
            'formula_version' => 'viabilidade-v1',
        ]);
    }

    public function find(int $viabilidadeId, int $scenarioId): ViabilidadeScenario
    {
        return $this->repository->findForViabilidadeOrFail($viabilidadeId, $scenarioId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ViabilidadeScenario $scenario, array $data): ViabilidadeScenario
    {
        if ($scenario->getAttribute('status') === 'completed') {
            throw new InvalidArgumentException('Cenário calculado não pode ser editado.');
        }

        $updates = [];
        if (array_key_exists('name', $data)) {
            $updates['name'] = $data['name'];
        }
        if (array_key_exists('scenario_type', $data)) {
            $updates['scenario_type'] = $data['scenario_type'];
        }
        if (array_key_exists('premises', $data)) {
            $overrides = $this->validatedOverrides($data['premises']);
            $updates['premises_snapshot'] = [
                'base_viabilidade_id' => $scenario->getAttribute('viabilidade_id'),
                'overrides' => $overrides,
            ];
            $updates['input_hash'] = hash('sha256', json_encode($overrides, JSON_THROW_ON_ERROR));
        }

        return $this->repository->update($scenario, $updates);
    }

    public function delete(ViabilidadeScenario $scenario): void
    {
        $this->repository->delete($scenario);
    }

    public function calculate(ViabilidadeScenario $scenario, User $actor): ViabilidadeScenario
    {
        $base = $this->viabilidadeRepository->findOrFail((int) $scenario->getAttribute('viabilidade_id'));
        $snapshot = $scenario->getAttribute('premises_snapshot');
        $overrides = is_array($snapshot) && is_array($snapshot['overrides'] ?? null)
            ? $snapshot['overrides']
            : [];

        $this->repository->update($scenario, ['status' => 'processing', 'error_message' => null]);

        try {
            $result = DB::transaction(function () use ($base, $overrides, $actor): array {
                $temporary = $this->temporaryViabilidade($base, $overrides, $actor);
                try {
                    return $this->viabilidadeService->recalcularDre($temporary, $actor)['dre_resultados'];
                } finally {
                    $this->viabilidadeRepository->delete($temporary);
                }
            });

            return $this->repository->update($scenario, [
                'status' => 'completed',
                'results' => $result,
                'calculated_by' => $actor->id,
                'calculated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->repository->update($scenario, [
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function promote(ViabilidadeScenario $scenario, User $actor): Viabilidade
    {
        if ($scenario->getAttribute('status') !== 'completed') {
            throw new InvalidArgumentException('Calcule o cenário antes de promovê-lo.');
        }

        $base = $this->viabilidadeRepository->findOrFail((int) $scenario->getAttribute('viabilidade_id'));
        $snapshot = $scenario->getAttribute('premises_snapshot');
        $overrides = is_array($snapshot) && is_array($snapshot['overrides'] ?? null)
            ? $snapshot['overrides']
            : [];
        $promoted = $this->viabilidadeService->duplicarViabilidade($base->id, $actor);
        $promoted = $this->viabilidadeService->atualizarViabilidadeComDre($promoted->id, $overrides, $actor)['viabilidade'];

        $this->repository->update($scenario, [
            'promoted_by' => $actor->id,
            'promoted_at' => now(),
        ]);

        return $promoted;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedOverrides(mixed $overrides): array
    {
        if (! is_array($overrides)) {
            throw new InvalidArgumentException('As premissas do cenário devem ser um objeto.');
        }

        $invalid = array_diff(array_keys($overrides), self::OVERRIDE_FIELDS);
        if ($invalid !== []) {
            throw new InvalidArgumentException('Premissa de cenário não permitida: '.implode(', ', $invalid));
        }

        return $overrides;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function temporaryViabilidade(Viabilidade $base, array $overrides, User $actor): Viabilidade
    {
        $attributes = $base->getAttributes();
        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at']);
        $attributes['status'] = 'rascunho';
        $attributes['approval_status'] = 'pendente';
        $attributes['is_current'] = false;
        $attributes['resultados_dre'] = null;
        $attributes['created_by'] = $actor->id;
        $attributes['updated_by'] = $actor->id;

        return $this->viabilidadeRepository->create(array_merge($attributes, $overrides));
    }
}
