<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Contrato;
use App\Models\Tenant\ContratoCondicao;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\NegociacaoAprovacao;
use App\Models\Tenant\NegociacaoOferta;
use App\Models\Tenant\User;
use App\Repositories\Tenant\NegotiationDealRoomRepository;
use Illuminate\Support\Collection;
use RuntimeException;

class NegotiationDealRoomService
{
    public function __construct(
        private readonly NegotiationDealRoomRepository $repository,
    ) {}

    public function negotiation(int $id): Negociacao
    {
        return $this->repository->negotiation($id);
    }

    public function contract(int $id): Contrato
    {
        return $this->repository->contract($id);
    }

    /** @return Collection<int, NegociacaoOferta> */
    public function offers(Negociacao $negotiation): Collection
    {
        return $this->repository->offers($negotiation);
    }

    public function offer(Negociacao $negotiation, int $offerId): NegociacaoOferta
    {
        return $this->repository->offer($negotiation, $offerId);
    }

    public function createOffer(Negociacao $negotiation, array $data, ?User $user): NegociacaoOferta
    {
        if (in_array($negotiation->status, ['encerrada', 'cancelada'], true)) {
            throw new RuntimeException('Não é possível criar oferta em uma negociação encerrada.');
        }

        return $this->repository->createOffer([
            ...$data,
            'negociacao_id' => $negotiation->id,
            'version' => $this->repository->nextOfferVersion($negotiation),
            'created_by' => $user?->id,
        ]);
    }

    public function acceptOffer(Negociacao $negotiation, int $offerId, ?User $user): NegociacaoOferta
    {
        $offer = $this->offer($negotiation, $offerId);
        if (in_array($offer->status, ['rejected', 'withdrawn'], true)) {
            throw new RuntimeException('Oferta rejeitada ou retirada não pode ser aceita.');
        }

        $accepted = $this->repository->updateOffer($offer, ['status' => 'accepted', 'accepted_at' => now()]);
        $this->repository->createEvent($negotiation, [
            'event_type' => 'offer.accepted',
            'payload_json' => ['offer_id' => $accepted->id, 'version' => $accepted->version],
            'user_id' => $user?->id,
            'happened_at' => now(),
        ]);

        return $accepted;
    }

    public function rejectOffer(Negociacao $negotiation, int $offerId, ?User $user): NegociacaoOferta
    {
        $offer = $this->offer($negotiation, $offerId);
        $rejected = $this->repository->updateOffer($offer, ['status' => 'rejected', 'rejected_at' => now()]);
        $this->repository->createEvent($negotiation, [
            'event_type' => 'offer.rejected',
            'payload_json' => ['offer_id' => $rejected->id, 'version' => $rejected->version],
            'user_id' => $user?->id,
            'happened_at' => now(),
        ]);

        return $rejected;
    }

    /** @return Collection<int, NegociacaoAprovacao> */
    public function approvals(Negociacao $negotiation): Collection
    {
        return $this->repository->approvals($negotiation);
    }

    public function saveApproval(Negociacao $negotiation, array $data, ?User $user): NegociacaoAprovacao
    {
        $decision = $data['decision'] ?? 'pending';

        return $this->repository->upsertApproval($negotiation, [
            ...$data,
            'decided_by' => $decision === 'pending' ? null : $user?->id,
            'decided_at' => $decision === 'pending' ? null : now(),
        ]);
    }

    /** @return Collection<int, ContratoCondicao> */
    public function conditions(Contrato $contract): Collection
    {
        return $this->repository->conditions($contract);
    }

    public function createCondition(Contrato $contract, array $data): ContratoCondicao
    {
        return $this->repository->createCondition([
            ...$data,
            'contrato_id' => $contract->id,
        ]);
    }

    public function updateCondition(Contrato $contract, int $conditionId, array $data): ContratoCondicao
    {
        return $this->repository->updateCondition($this->repository->condition($contract, $conditionId), [
            ...$data,
            'fulfilled_at' => ($data['status'] ?? null) === 'fulfilled' ? now() : null,
        ]);
    }
}
