<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\PremissasViabilidade;
use App\Repositories\Contracts\PremissasViabilidadeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PremissasViabilidadeCrudService
{
    public function __construct(
        private readonly PremissasViabilidadeRepositoryInterface $repository,
    ) {}

    public function list(?string $perfil, int $perPage, ?bool $ativo = null): LengthAwarePaginator
    {
        return $this->repository->paginate($perfil, $perPage, $ativo);
    }

    public function findById(int $id): ?PremissasViabilidade
    {
        return $this->repository->findById($id);
    }

    /**
     * @return array{
     *   current: PremissasViabilidade,
     *   previous: PremissasViabilidade|null,
     *   versions: list<PremissasViabilidade>
     * }
     */
    public function history(PremissasViabilidade $premissa): array
    {
        return [
            'current' => $premissa,
            'previous' => $this->repository->findPreviousVersion($premissa),
            'versions' => $this->repository->listVersions($premissa),
        ];
    }

    public function create(array $data): PremissasViabilidade
    {
        unset($data['versao']);
        $data['versao'] = $this->repository->nextVersion((string) $data['perfil_financiamento']);
        $data['vigente_em'] = $this->resolveNovaVigencia($data['vigente_em'] ?? null);

        return $this->repository->create($data);
    }

    public function update(PremissasViabilidade $premissa, array $data): PremissasViabilidade
    {
        return DB::transaction(function () use ($premissa, $data): PremissasViabilidade {
            $novaVigencia = $this->resolveNovaVigencia($data['vigente_em'] ?? null);
            $encerramentoAnterior = Carbon::parse($novaVigencia)
                ->subDay()
                ->toDateString();

            $payload = $premissa->only($premissa->getFillable());
            unset($payload['versao']);

            $payload = array_merge($payload, $data, [
                'versao' => $this->repository->nextVersion(
                    (string) ($data['perfil_financiamento'] ?? $premissa->perfil_financiamento->value)
                ),
                'vigente_em' => $novaVigencia,
                'ativo' => $data['ativo'] ?? true,
            ]);

            $this->repository->closeCurrentVersion($premissa, $encerramentoAnterior);

            return $this->repository->create($payload);
        });
    }

    public function delete(PremissasViabilidade $premissa): void
    {
        $this->repository->delete($premissa);
    }

    private function resolveNovaVigencia(?string $vigenteEm): string
    {
        if (is_string($vigenteEm) && $vigenteEm !== '') {
            return Carbon::parse($vigenteEm)->toDateString();
        }

        return now()->toDateString();
    }
}
