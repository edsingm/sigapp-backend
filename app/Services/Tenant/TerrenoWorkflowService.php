<?php

namespace App\Services\Tenant;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Repositories\Tenant\TerrenoRepository;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TerrenoWorkflowService
{
    public function __construct(
        private readonly TerrenoRepository $repository,
        private readonly LandWorkflowService $workflowService,
    ) {}

    public function show(int|string $id): Terreno
    {
        return $this->repository->loadWorkflowRelations(
            $this->repository->findOrFail($id)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function workflowState(int|string $id): array
    {
        $terreno = $this->show($id);
        $statusCode = $terreno->workflow_status_code ?: WorkflowStatus::EM_ANALISE->value;
        $statusMeta = LandWorkflowService::statuses()[$statusCode]
            ?? LandWorkflowService::statuses()[WorkflowStatus::EM_ANALISE->value];
        $enteredAt = $terreno->workflow_status_changed_at ?? $terreno->created_at;
        $ageDays = $enteredAt?->diffInDays(now()) ?? 0;
        $options = $this->workflowService->transitionOptions($terreno);
        $isTerminal = in_array($statusCode, [
            ...WorkflowStatus::closure(),
            WorkflowStatus::LEGALIZADO_FINALIZADO->value,
        ], true);

        return [
            'status' => [
                'code' => $statusCode,
                'label' => $statusMeta['label'],
            ],
            'phase' => [
                'code' => $statusMeta['stage'],
                'label' => $statusMeta['stage'],
            ],
            'entered_at' => $enteredAt?->toIso8601String(),
            'age_days' => $ageDays,
            'is_overdue' => false,
            'is_terminal' => $isTerminal,
            'primary_action' => $options['available'][0] ?? null,
            'allowed_actions' => $options['available'],
            'blocked_actions' => $options['blocked'],
            'responsible' => $terreno->responsavel ? [
                'id' => $terreno->responsavel->id,
                'name' => $terreno->responsavel->name,
                'email' => $terreno->responsavel->email,
            ] : null,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(int|string $id): array
    {
        $terreno = $this->show($id);
        $checklist = $this->workflowService->checklist($terreno);
        $options = $this->workflowService->transitionOptions($terreno);
        $items = array_map(static function (array $item): array {
            return [
                ...$item,
                'state' => $item['completed'] ? 'complete' : 'missing',
                'classification' => $item['completed'] ? 'complete' : 'warning',
                'reason' => $item['completed'] ? null : 'Pré-requis ainda não preenchido.',
            ];
        }, $checklist);
        $missingCount = count(array_filter($items, static fn (array $item): bool => ! $item['completed']));
        $blockingCount = count($options['blocked']);

        return [
            'status' => $blockingCount > 0 ? 'blocked' : ($missingCount > 0 ? 'warning' : 'ready'),
            'items' => $items,
            'blocking_count' => $blockingCount,
            'warning_count' => $missingCount,
            'missing_data_count' => $missingCount,
            'blocked_actions' => $options['blocked'],
            'catalog_version' => 'workflow-v1',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function transition(int|string $id, array $data, User $actor): Terreno
    {
        $terreno = $this->repository->loadWorkflowRelations(
            $this->repository->findOrFail($id)
        );

        try {
            return $this->workflowService->transition(
                $terreno,
                (string) $data['target_status'],
                $actor,
                isset($data['reason_code']) ? (string) $data['reason_code'] : null,
                isset($data['reason_notes']) ? (string) $data['reason_notes'] : null,
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'target_status' => [$exception->getMessage()],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateQualification(int|string $id, array $data, User $actor): Terreno
    {
        $terreno = $this->repository->findOrFail($id);
        $currentQualification = is_array($terreno->qualification_data) ? $terreno->qualification_data : [];
        $qualificationData = $currentQualification;

        foreach (['urbanistic_preliminary', 'commercial', 'desired_product', 'preliminary_risks', 'attachments'] as $section) {
            if (array_key_exists($section, $data)) {
                $qualificationData[$section] = $data[$section];
            }
        }

        $payload = [
            'qualification_data' => $qualificationData,
            'updated_by' => $actor->id,
        ];

        if (($data['mark_as_completed'] ?? null) === true) {
            $payload['qualification_completed_at'] = now();
            $payload['qualification_completed_by'] = $actor->id;
        }

        if (($data['mark_as_completed'] ?? null) === false) {
            $payload['qualification_completed_at'] = null;
            $payload['qualification_completed_by'] = null;
        }

        $terreno = $this->repository->update($terreno, $payload);

        return $this->repository->loadDetailRelations($terreno);
    }
}
