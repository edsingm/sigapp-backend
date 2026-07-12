<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\AiContextRecommendation;
use App\Models\Tenant\User;
use App\Repositories\Tenant\AiContextRecommendationRepository;
use App\Repositories\Tenant\TerrenoRepository;
use App\Services\Ai\Tools\AiScoringService;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ContextualAiService
{
    private const INTENTS = ['score', 'readiness', 'workflow'];

    public function __construct(
        private readonly AiContextRecommendationRepository $repository,
        private readonly TerrenoRepository $terrenos,
        private readonly AiScoringService $scoring,
        private readonly TerrenoWorkflowService $workflow,
        private readonly TaskService $tasks,
    ) {}

    /** @param array<string, mixed> $data */
    public function context(User $user, array $data): AiContextRecommendation
    {
        $entityType = (string) $data['entity_type'];
        $entityId = (int) $data['entity_id'];
        $intent = (string) $data['intent'];
        if ($entityType !== 'terreno' || ! in_array($intent, self::INTENTS, true)) {
            throw new RuntimeException('Entidade ou intenção de IA contextual não permitida.');
        }

        $terreno = $this->terrenos->findOrFail($entityId);
        Gate::forUser($user)->authorize('view', $terreno);
        $output = match ($intent) {
            'score' => $this->scoring->getScore($terreno),
            'readiness' => $this->workflow->readiness($entityId),
            'workflow' => $this->workflow->workflowState($entityId),
        };

        return $this->repository->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'intent' => $intent,
            'parameters' => $data['parameters'] ?? [],
            'input_hash' => hash('sha256', json_encode([$entityType, $entityId, $intent, $data['parameters'] ?? []], JSON_THROW_ON_ERROR)),
            'output' => [
                'result' => $output,
                'sources' => [$entityType.':'.$entityId],
                'confidence' => $intent === 'score' ? 0.75 : 0.65,
                'limitations' => ['Resultado heurístico; revise os fatores antes de aplicar uma ação.'],
            ],
            'status' => 'proposed',
            'action' => $data['action'] ?? null,
            'created_by' => $user->id,
            'expires_at' => now()->addHour(),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function apply(User $user, int $id, array $data): AiContextRecommendation
    {
        $recommendation = $this->repository->findForUserOrFail($user->id, $id);
        if ($recommendation->status !== 'proposed' || ($recommendation->expires_at?->isPast() ?? false)) {
            throw new RuntimeException('Recomendação não está disponível para aplicação.');
        }
        if (($data['confirmation'] ?? false) !== true) {
            throw new RuntimeException('A aplicação exige confirmação explícita.');
        }

        $parameters = is_array($recommendation->parameters) ? $recommendation->parameters : [];
        if ($recommendation->action === 'create_task') {
            $title = $parameters['task_title'] ?? null;
            if (! is_string($title) || $title === '') {
                throw new RuntimeException('A recomendação de tarefa não possui título válido.');
            }
            $this->tasks->create([
                'terreno_id' => $recommendation->entity_id,
                'related_type' => 'terreno',
                'related_id' => $recommendation->entity_id,
                'title' => $title,
                'description' => $parameters['task_description'] ?? null,
                'priority' => $parameters['priority'] ?? 'medium',
            ], $user->id);
        } elseif ($recommendation->action !== null) {
            throw new RuntimeException('Ação contextual não permitida.');
        }

        return $this->repository->update($recommendation, [
            'status' => 'applied',
            'applied_by' => $user->id,
            'justification' => $data['justification'] ?? null,
            'applied_at' => now(),
        ]);
    }
}
