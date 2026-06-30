<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\PremissasViabilidade;
use App\Repositories\Contracts\PremissasViabilidadeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PremissasViabilidadeRepository implements PremissasViabilidadeRepositoryInterface
{
    public function paginate(?string $perfil, int $perPage, ?bool $ativo = null): LengthAwarePaginator
    {
        $query = PremissasViabilidade::query();

        if ($perfil !== null && $perfil !== '') {
            $query->where('perfil_financiamento', $perfil);
        }

        if ($ativo !== null) {
            $query->where('ativo', $ativo);
        }

        return $query
            ->with(['createdBy', 'updatedBy'])
            ->orderBy('perfil_financiamento')
            ->orderBy('versao', 'desc')
            ->paginate($perPage);
    }

    public function findById(int $id): ?PremissasViabilidade
    {
        return PremissasViabilidade::query()
            ->with(['createdBy', 'updatedBy'])
            ->find($id);
    }

    public function findPreviousVersion(PremissasViabilidade $premissa): ?PremissasViabilidade
    {
        $query = PremissasViabilidade::query()
            ->with(['createdBy', 'updatedBy'])
            ->where('perfil_financiamento', $premissa->perfil_financiamento)
            ->where('versao', '<', $premissa->versao)
            ->orderByDesc('versao');

        $nome = $premissa->getAttribute('nome');
        if (is_string($nome) && $nome !== '') {
            $candidate = (clone $query)
                ->where('nome', $nome)
                ->first();

            if ($candidate instanceof PremissasViabilidade) {
                return $candidate;
            }
        }

        return $query->first();
    }

    /**
     * @return list<PremissasViabilidade>
     */
    public function listVersions(PremissasViabilidade $premissa): array
    {
        $query = PremissasViabilidade::query()
            ->with(['createdBy', 'updatedBy'])
            ->where('perfil_financiamento', $premissa->perfil_financiamento)
            ->orderByDesc('versao');

        $nome = $premissa->getAttribute('nome');
        if (is_string($nome) && $nome !== '') {
            $query->where('nome', $nome);
        }

        /** @var list<PremissasViabilidade> $items */
        $items = $query->get()->all();

        return $items;
    }

    public function create(array $data): PremissasViabilidade
    {
        $premissa = PremissasViabilidade::query()->create($data);

        return $premissa->load(['createdBy', 'updatedBy']);
    }

    public function nextVersion(string $perfil): int
    {
        $ultimaVersao = PremissasViabilidade::query()
            ->where('perfil_financiamento', $perfil)
            ->max('versao');

        return (int) $ultimaVersao + 1;
    }

    public function closeCurrentVersion(PremissasViabilidade $premissa, string $encerradaEm): void
    {
        $premissa->update([
            'ativo' => false,
            'encerrada_em' => $encerradaEm,
        ]);
    }

    public function update(PremissasViabilidade $premissa, array $data): PremissasViabilidade
    {
        $premissa->update($data);

        return $premissa->refresh()->load(['createdBy', 'updatedBy']);
    }

    public function delete(PremissasViabilidade $premissa): void
    {
        $premissa->delete();
    }
}
