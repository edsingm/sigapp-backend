<?php

namespace App\Services\Tenant;

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
