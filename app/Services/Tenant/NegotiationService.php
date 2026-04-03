<?php

namespace App\Services\Tenant;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\Contrato;
use App\Models\Tenant\ContratoParte;
use App\Models\Tenant\EntityActivity;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\NegociacaoEvento;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NegotiationService
{
    /**
     * Lista as negociações com filtros e paginação.
     */
    public function listNegotiations(array $filters = []): LengthAwarePaginator
    {
        $query = Negociacao::query()->with(['terreno', 'eventos', 'contratos.partes']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('terreno', function ($builder) use ($search) {
                $builder->where('nome', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Cria uma nova negociação para um terreno.
     *
     * @param  array<string, mixed>  $data
     */
    public function createNegotiation(array $data, ?User $user, LandWorkflowService $workflowService): Negociacao
    {
        return DB::transaction(function () use ($data, $user, $workflowService) {
            $terreno = Terreno::query()->with('comiteAtual')->findOrFail($data['terreno_id']);
            $decision = $terreno->comiteAtual?->final_decision;

            if (! in_array($decision, ['aprovado_comite', 'aprovado_com_ressalvas'], true)) {
                throw new RuntimeException('Negociação exige comitê aprovado.');
            }

            $negociacao = Negociacao::create([
                'terreno_id' => $terreno->id,
                'status' => $data['status'] ?? WorkflowStatus::NEGOCIACAO_MINUTA->value,
                'proposal_value' => $data['proposal_value'] ?? null,
                'business_model' => $data['business_model'] ?? null,
                'started_at' => $data['started_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            $workflowService->transition($terreno, $negociacao->status, $user, 'negotiation_started', $negociacao->notes);

            return $negociacao->fresh(['terreno', 'eventos', 'contratos.partes']);
        });
    }

    /**
     * Atualiza os dados de uma negociação existente.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateNegotiation(Negociacao $negociacao, array $data, ?User $user, ?LandWorkflowService $workflowService = null): Negociacao
    {
        $negociacao->update([
            'status' => $data['status'] ?? $negociacao->status,
            'proposal_value' => $data['proposal_value'] ?? $negociacao->proposal_value,
            'business_model' => $data['business_model'] ?? $negociacao->business_model,
            'started_at' => $data['started_at'] ?? $negociacao->started_at,
            'closed_at' => $data['closed_at'] ?? $negociacao->closed_at,
            'notes' => $data['notes'] ?? $negociacao->notes,
            'updated_by' => $user?->id,
        ]);

        if ($workflowService && isset($data['status'])) {
            $workflowService->transition(
                $negociacao->terreno()->firstOrFail(),
                $data['status'],
                $user,
                'negotiation_updated',
                $data['notes'] ?? null,
            );
        }

        return $negociacao->fresh(['terreno', 'eventos', 'contratos.partes']);
    }

    /**
     * Adiciona um evento ao histórico da negociação.
     *
     * @param  array<string, mixed>  $data
     */
    public function addEvent(Negociacao $negociacao, array $data, ?User $user): NegociacaoEvento
    {
        return NegociacaoEvento::create([
            'negociacao_id' => $negociacao->id,
            'event_type' => $data['event_type'],
            'payload_json' => $data['payload_json'] ?? null,
            'notes' => $data['notes'] ?? null,
            'user_id' => $user?->id,
            'happened_at' => $data['happened_at'] ?? now(),
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
            $contract = Contrato::create($payload);
        } else {
            $contract->update($payload);
        }

        if (! empty($data['partes']) && is_array($data['partes'])) {
            $contract->partes()->delete();
            foreach ($data['partes'] as $parte) {
                ContratoParte::create([
                    'contrato_id' => $contract->id,
                    'name' => $parte['name'],
                    'document' => $parte['document'] ?? null,
                    'party_type' => $parte['party_type'] ?? null,
                    'signer_name' => $parte['signer_name'] ?? null,
                    'signer_document' => $parte['signer_document'] ?? null,
                ]);
            }
        }

        return $contract->fresh(['terreno', 'negociacao', 'partes']);
    }

    /**
     * Registra a assinatura do contrato e atualiza o workflow do terreno.
     */
    public function signContract(Contrato $contract, ?User $user, LandWorkflowService $workflowService): Contrato
    {
        if (! $contract->contract_type || ! $contract->file_path || ! $contract->partes()->exists()) {
            throw new RuntimeException('Contrato assinado exige tipo, partes e documento.');
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

        EntityActivity::create([
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

        return $contract->fresh(['terreno', 'negociacao', 'partes']);
    }
}
