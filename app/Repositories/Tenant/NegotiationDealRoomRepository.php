<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\Contrato;
use App\Models\Tenant\ContratoCondicao;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\NegociacaoAprovacao;
use App\Models\Tenant\NegociacaoEvento;
use App\Models\Tenant\NegociacaoOferta;
use Illuminate\Database\Eloquent\Collection;

class NegotiationDealRoomRepository
{
    public function negotiation(int $id): Negociacao
    {
        return Negociacao::query()->findOrFail($id);
    }

    public function contract(int $id): Contrato
    {
        return Contrato::query()->findOrFail($id);
    }

    /** @return Collection<int, NegociacaoOferta> */
    public function offers(Negociacao $negotiation): Collection
    {
        $ids = $negotiation->ofertas()->pluck('id')->all();
        $offers = NegociacaoOferta::query()->findMany($ids);
        $offers->load(['creator', 'previousOffer']);

        return new Collection($offers->sortByDesc('version')->values()->all());
    }

    public function offer(Negociacao $negotiation, int $id): NegociacaoOferta
    {
        return $negotiation->ofertas()->with(['creator', 'previousOffer'])->findOrFail($id);
    }

    public function nextOfferVersion(Negociacao $negotiation): int
    {
        return ((int) $negotiation->ofertas()->max('version')) + 1;
    }

    public function createOffer(array $data): NegociacaoOferta
    {
        return NegociacaoOferta::create($data)->load(['creator', 'previousOffer']);
    }

    public function updateOffer(NegociacaoOferta $offer, array $data): NegociacaoOferta
    {
        $offer->update($data);

        return $offer->fresh(['creator', 'previousOffer']) ?? throw new \RuntimeException('Oferta não encontrada após atualização.');
    }

    /** @return Collection<int, NegociacaoAprovacao> */
    public function approvals(Negociacao $negotiation): Collection
    {
        return $negotiation->aprovacoes()->with('decidedBy')->get();
    }

    public function upsertApproval(Negociacao $negotiation, array $data): NegociacaoAprovacao
    {
        return $negotiation->aprovacoes()->updateOrCreate(
            ['area' => $data['area']],
            $data,
        )->load('decidedBy');
    }

    public function createEvent(Negociacao $negotiation, array $data): NegociacaoEvento
    {
        return NegociacaoEvento::query()->create([
            ...$data,
            'negociacao_id' => $negotiation->id,
        ]);
    }

    /** @return Collection<int, ContratoCondicao> */
    public function conditions(Contrato $contract): Collection
    {
        return $contract->condicoes()->with('responsavel')->latest()->get();
    }

    public function createCondition(array $data): ContratoCondicao
    {
        return ContratoCondicao::create($data)->load('responsavel');
    }

    public function condition(Contrato $contract, int $id): ContratoCondicao
    {
        return $contract->condicoes()->findOrFail($id);
    }

    public function updateCondition(ContratoCondicao $condition, array $data): ContratoCondicao
    {
        $condition->update($data);

        return $condition->fresh('responsavel') ?? throw new \RuntimeException('Condição não encontrada após atualização.');
    }
}
