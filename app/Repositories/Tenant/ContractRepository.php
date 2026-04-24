<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Contrato;
use App\Models\Tenant\ContratoParte;
use App\Models\Tenant\EntityActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ContractRepository
{
    /**
     * @var list<string>
     */
    private const DETAIL_RELATIONS = [
        'terreno',
        'negociacao',
        'partes',
    ];

    public function findOrFail(int|string $id): Contrato
    {
        return Contrato::query()->findOrFail($id);
    }

    /**
     * @param  array{search?: string|null, per_page?: int|null}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Contrato::query()->with(self::DETAIL_RELATIONS);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('contract_number', 'like', "%{$search}%");
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 10));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contrato
    {
        return Contrato::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contrato $contract, array $data): Contrato
    {
        $contract->update($data);

        return $contract->refresh();
    }

    public function clearPartes(Contrato $contract): void
    {
        $contract->partes()->delete();
    }

    /**
     * @param  array<string, mixed>  $parte
     */
    public function createParte(Contrato $contract, array $parte): ContratoParte
    {
        return ContratoParte::query()->create([
            'contrato_id' => $contract->id,
            'name' => $parte['name'],
            'document' => $parte['document'] ?? null,
            'party_type' => $parte['party_type'] ?? null,
            'signer_name' => $parte['signer_name'] ?? null,
            'signer_document' => $parte['signer_document'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createActivity(array $payload): EntityActivity
    {
        return EntityActivity::query()->create($payload);
    }

    public function loadDetail(Contrato $contract): Contrato
    {
        return $contract->fresh(self::DETAIL_RELATIONS);
    }
}
