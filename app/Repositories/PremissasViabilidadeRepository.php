<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\PremissasViabilidade;
use App\Models\Tenant\Viabilidade;
use App\Repositories\Contracts\PremissasViabilidadeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
            ->orderByDesc('vigente_em')
            ->orderByDesc('versao')
            ->orderByDesc('id')
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
        $nome = $premissa->getAttribute('nome');
        if (is_string($nome) && $nome !== '') {
            $candidate = $this->previousVersionQuery($premissa)->where('nome', $nome)->first();

            if ($candidate !== null) {
                return $candidate;
            }
        }

        return $this->previousVersionQuery($premissa)->first();
    }

    /**
     * @return Builder<PremissasViabilidade>
     */
    private function previousVersionQuery(PremissasViabilidade $premissa): Builder
    {
        return PremissasViabilidade::query()
            ->with(['createdBy', 'updatedBy'])
            ->where('perfil_financiamento', $premissa->perfil_financiamento)
            ->where('versao', '<', $premissa->versao)
            ->orderByDesc('versao')
            ->orderByDesc('id');
    }

    /**
     * @return list<PremissasViabilidade>
     */
    public function listVersions(PremissasViabilidade $premissa): array
    {
        $query = PremissasViabilidade::query()
            ->with(['createdBy', 'updatedBy'])
            ->where('perfil_financiamento', $premissa->perfil_financiamento)
            ->orderByDesc('versao')
            ->orderByDesc('id');

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

    public function closeIntervalBefore(string $perfil, string $novaVigenteEm, ?int $exceptId = null): void
    {
        $encerradaEm = Carbon::parse($novaVigenteEm)->subDay()->toDateString();
        $hoje = now()->toDateString();

        $query = PremissasViabilidade::query()
            ->where('perfil_financiamento', $perfil)
            ->where('ativo', true)
            // Só encerra quem já está (ou estava) em vigor antes da nova data.
            ->where(function (Builder $q) use ($novaVigenteEm): void {
                $q->whereNull('vigente_em')
                    ->orWhereDate('vigente_em', '<', $novaVigenteEm);
            })
            ->where(function (Builder $q) use ($novaVigenteEm): void {
                // Intervalos abertos ou que cruzam a nova vigência.
                $q->whereNull('encerrada_em')
                    ->orWhereDate('encerrada_em', '>=', $novaVigenteEm);
            })
            ->when($exceptId !== null, fn (Builder $q) => $q->where('id', '!=', $exceptId));

        $query->get()->each(function (PremissasViabilidade $premissa) use ($encerradaEm, $hoje): void {
            $payload = ['encerrada_em' => $encerradaEm];
            // Só desativa do catálogo se o intervalo já acabou.
            if ($encerradaEm < $hoje) {
                $payload['ativo'] = false;
            }
            $premissa->update($payload);
        });
    }

    public function findOverlapping(
        string $perfil,
        string $vigenteEm,
        ?string $encerradaEm,
        ?int $exceptId = null
    ): Collection {
        $fim = $encerradaEm ?? '9999-12-31';

        // Intervalos ativos do mesmo perfil: [vigente_em, encerrada_em] sem sobreposição.
        // [a,b] sobrepõe [c,d] ⇔ a <= d e c <= b (null = aberto).
        return PremissasViabilidade::query()
            ->where('perfil_financiamento', $perfil)
            ->where('ativo', true)
            ->when($exceptId !== null, fn (Builder $q) => $q->where('id', '!=', $exceptId))
            ->where(function (Builder $q) use ($vigenteEm, $fim): void {
                $q->where(function (Builder $start) use ($fim): void {
                    $start->whereNull('vigente_em')
                        ->orWhereDate('vigente_em', '<=', $fim);
                })->where(function (Builder $end) use ($vigenteEm): void {
                    $end->whereNull('encerrada_em')
                        ->orWhereDate('encerrada_em', '>=', $vigenteEm);
                });
            })
            ->get();
    }

    public function findActiveForPerfilAt(string $perfil, ?string $date = null): ?PremissasViabilidade
    {
        $date ??= now()->toDateString();

        return PremissasViabilidade::query()
            ->where('perfil_financiamento', $perfil)
            ->where(function (Builder $q) use ($date): void {
                $q->whereDate('vigente_em', '<=', $date)
                    ->orWhereNull('vigente_em');
            })
            ->where(function (Builder $q) use ($date): void {
                $q->whereDate('encerrada_em', '>=', $date)
                    ->orWhereNull('encerrada_em');
            })
            // Preferência determinística: mais recente, maior versão, maior id.
            ->orderByDesc('vigente_em')
            ->orderByDesc('versao')
            ->orderByDesc('id')
            ->first();
    }

    public function isReferencedInSnapshots(int $premissaId): bool
    {
        // Busca em lotes pequenos; snapshot JSON não tem índice de premissa_id.
        return Viabilidade::query()
            ->whereNotNull('premissas_snapshot')
            ->orderBy('id')
            ->limit(500)
            ->get(['id', 'premissas_snapshot'])
            ->contains(static function (Viabilidade $viabilidade) use ($premissaId): bool {
                $snapshot = $viabilidade->premissas_snapshot;
                if (! is_array($snapshot)) {
                    return false;
                }

                $id = data_get($snapshot, 'premissas.id')
                    ?? data_get($snapshot, 'premissa_id')
                    ?? data_get($snapshot, 'inputs.premissa_id');

                return (int) $id === $premissaId;
            });
    }

    public function deactivate(PremissasViabilidade $premissa, ?string $encerradaEm = null): PremissasViabilidade
    {
        $premissa->update([
            'ativo' => false,
            'encerrada_em' => $encerradaEm ?? now()->toDateString(),
        ]);

        return $premissa->refresh()->load(['createdBy', 'updatedBy']);
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
