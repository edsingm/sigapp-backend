<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Negociacao;
use App\Models\Tenant\NegociacaoEvento;
use App\Models\Tenant\Terreno;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NegotiationRepository
{
    /**
     * @var list<string>
     */
    private const DETAIL_RELATIONS = [
        'terreno',
        'eventos',
        'contratos.partes',
    ];

    public function findOrFail(int|string $id): Negociacao
    {
        return Negociacao::query()->findOrFail($id);
    }

    public function findTerrenoForNegotiationOrFail(int|string $id): Terreno
    {
        return Terreno::query()->with('comiteAtual')->findOrFail($id);
    }

    /**
     * @param  array{status?: string|null, search?: string|null, per_page?: int|null}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Negociacao::query()->with(self::DETAIL_RELATIONS);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('terreno', function ($builder) use ($search): void {
                $builder->where('nome', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')->paginate((int) ($filters['per_page'] ?? 10));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Negociacao
    {
        return Negociacao::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Negociacao $negociacao, array $data): Negociacao
    {
        $negociacao->update($data);

        return $negociacao->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createEvent(Negociacao $negociacao, array $data): NegociacaoEvento
    {
        return NegociacaoEvento::query()->create([
            'negociacao_id' => $negociacao->id,
            'event_type' => $data['event_type'],
            'payload_json' => $data['payload_json'] ?? null,
            'notes' => $data['notes'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'happened_at' => $data['happened_at'] ?? now(),
        ]);
    }

    public function loadDetail(Negociacao $negociacao): Negociacao
    {
        return $negociacao->fresh(self::DETAIL_RELATIONS);
    }
}
