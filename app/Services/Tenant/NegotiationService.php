<?php

namespace App\Services\Tenant;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\Contrato;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\NegociacaoEvento;
use App\Models\Tenant\User;
use App\Repositories\Tenant\ContractRepository;
use App\Repositories\Tenant\NegotiationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NegotiationService
{
    public function __construct(
        private readonly NegotiationRepository $repository,
        private readonly ContractRepository $contractRepository,
        private readonly LandWorkflowService $workflowService,
    ) {}

    /**
     * Lista as negociações com filtros e paginação.
     */
    public function listNegotiations(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function findOrFail(int|string $id): Negociacao
    {
        return $this->repository->findOrFail($id);
    }

    public function findContractOrFail(int|string $id): Contrato
    {
        return $this->contractRepository->findOrFail($id);
    }

    public function showById(int|string $id): Negociacao
    {
        return $this->repository->loadDetail($this->repository->findOrFail($id));
    }

    public function showContractById(int|string $id): Contrato
    {
        return $this->contractRepository->loadDetail($this->contractRepository->findOrFail($id));
    }

    /**
     * Lista os contratos com filtros e paginação.
     */
    public function listContracts(array $filters = []): LengthAwarePaginator
    {
        return $this->contractRepository->paginate($filters);
    }

    /**
     * Cria uma nova negociação para um terreno.
     *
     * @param  array<string, mixed>  $data
     */
    public function createNegotiation(array $data, ?User $user): Negociacao
    {
        return DB::transaction(function () use ($data, $user) {
            $terreno = $this->repository->findTerrenoForNegotiationOrFail($data['terreno_id']);
            $decision = $terreno->comiteAtual?->final_decision;

            if (! in_array($decision, ['aprovado_comite', 'aprovado_com_ressalvas'], true)) {
                throw ValidationException::withMessages([
                    'terreno_id' => ['Negociação exige comitê aprovado.'],
                ]);
            }

            $negociacao = $this->repository->create([
                'terreno_id' => $terreno->id,
                'status' => $data['status'] ?? WorkflowStatus::NEGOCIACAO_MINUTA->value,
                'proposal_value' => $data['proposal_value'] ?? null,
                'business_model' => $data['business_model'] ?? null,
                'started_at' => $data['started_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            $this->workflowService->transition($terreno, $negociacao->status, $user, 'negotiation_started', $negociacao->notes);

            return $this->repository->loadDetail($negociacao);
        });
    }

    /**
     * Atualiza os dados de uma negociação existente.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateNegotiation(Negociacao $negociacao, array $data, ?User $user): Negociacao
    {
        $negociacao = $this->repository->update($negociacao, [
            'status' => $data['status'] ?? $negociacao->status,
            'proposal_value' => $data['proposal_value'] ?? $negociacao->proposal_value,
            'business_model' => $data['business_model'] ?? $negociacao->business_model,
            'started_at' => $data['started_at'] ?? $negociacao->started_at,
            'closed_at' => $data['closed_at'] ?? $negociacao->closed_at,
            'notes' => $data['notes'] ?? $negociacao->notes,
            'updated_by' => $user?->id,
        ]);

        if (isset($data['status'])) {
            $this->workflowService->transition(
                $negociacao->terreno()->firstOrFail(),
                $data['status'],
                $user,
                'negotiation_updated',
                $data['notes'] ?? null,
            );
        }

        return $this->repository->loadDetail($negociacao);
    }

    /**
     * Adiciona um evento ao histórico da negociação.
     *
     * @param  array<string, mixed>  $data
     */
    public function addEvent(Negociacao $negociacao, array $data, ?User $user): NegociacaoEvento
    {
        return $this->repository->createEvent($negociacao, [
            ...$data,
            'user_id' => $user?->id,
        ]);
    }

    /**
     * Cria ou atualiza um contrato vinculado à negociação.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOrUpdateContract(?Contrato $contract, array $data, ?User $user): Contrato
    {
        $payload = [
            'terreno_id' => $data['terreno_id'],
            'negociacao_id' => $data['negociacao_id'] ?? null,
            'contract_type' => $data['contract_type'] ?? null,
            'contract_number' => $data['contract_number'] ?? null,
            'signed_at' => $data['signed_at'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => $data['status'] ?? ($contract?->status ?? WorkflowStatus::NEGOCIACAO_MINUTA->value),
            'file_path' => $data['file_path'] ?? null,
            'notes' => $data['notes'] ?? null,
            'updated_by' => $user?->id,
        ];

        if (! $contract) {
            $payload['created_by'] = $user?->id;
            $contract = $this->contractRepository->create($payload);
        } else {
            $contract = $this->contractRepository->update($contract, $payload);
        }

        if (! empty($data['partes']) && is_array($data['partes'])) {
            $this->contractRepository->clearPartes($contract);
            foreach ($data['partes'] as $parte) {
                $this->contractRepository->createParte($contract, $parte);
            }
        }

        return $this->contractRepository->loadDetail($contract);
    }

    /**
     * Registra a assinatura do contrato e atualiza o workflow do terreno.
     */
    public function signContract(Contrato $contract, ?User $user, LandWorkflowService $workflowService): Contrato
    {
        if (! $contract->contract_type || ! $contract->file_path || ! $contract->partes()->exists()) {
            throw ValidationException::withMessages([
                'contract' => ['Contrato assinado exige tipo, partes e documento.'],
            ]);
        }

        $contract->update([
            'signed_at' => $contract->signed_at ?? now(),
            'status' => WorkflowStatus::CONTRATO_ASSINADO->value,
            'updated_by' => $user?->id,
        ]);

        $workflowService->transition(
            $contract->terreno()->firstOrFail(),
            WorkflowStatus::CONTRATO_ASSINADO->value,
            $user,
            'contract_signed',
            $contract->notes,
            ['contract' => $contract->fresh('partes')],
        );

        $this->contractRepository->createActivity([
            'terreno_id' => $contract->terreno_id,
            'entity_type' => Contrato::class,
            'entity_id' => $contract->id,
            'action' => 'contract.signed',
            'user_id' => $user?->id,
            'summary' => 'Contrato assinado.',
            'payload_json' => [
                'contract_type' => $contract->contract_type,
                'signed_at' => $contract->signed_at,
            ],
            'happened_at' => now(),
        ]);

        return $this->contractRepository->loadDetail($contract);
    }
}
