<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Exceptions\PremissasViabilidadeException;
use App\Models\Tenant\PremissasViabilidade;
use App\Repositories\Contracts\PremissasViabilidadeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

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
        return DB::transaction(function () use ($data): PremissasViabilidade {
            unset($data['versao']);
            $perfil = (string) $data['perfil_financiamento'];
            $data['versao'] = $this->repository->nextVersion($perfil);
            $data['vigente_em'] = $this->resolveNovaVigencia($data['vigente_em'] ?? null);
            $data['encerrada_em'] = $this->normalizeEncerrada($data['encerrada_em'] ?? null, $data['vigente_em']);
            $data['ativo'] = array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true;

            // Fecha a anterior na véspera; se a nova for futura, a atual permanece vigente até lá.
            $this->repository->closeIntervalBefore($perfil, $data['vigente_em']);
            $this->assertNoOverlap($perfil, $data['vigente_em'], $data['encerrada_em']);

            return $this->repository->create($data);
        });
    }

    public function update(PremissasViabilidade $premissa, array $data): PremissasViabilidade
    {
        return DB::transaction(function () use ($premissa, $data): PremissasViabilidade {
            $novaVigencia = $this->resolveNovaVigencia($data['vigente_em'] ?? null);
            $perfil = (string) ($data['perfil_financiamento'] ?? $premissa->perfil_financiamento->value
                ?? $premissa->getAttribute('perfil_financiamento'));
            $encerrada = $this->normalizeEncerrada($data['encerrada_em'] ?? null, $novaVigencia);

            $payload = $premissa->only($premissa->getFillable());
            unset($payload['versao']);

            $payload = array_merge($payload, $data, [
                'versao' => $this->repository->nextVersion($perfil),
                'perfil_financiamento' => $perfil,
                'vigente_em' => $novaVigencia,
                'encerrada_em' => $encerrada,
                'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
            ]);

            // Fecha intervalos concorrentes e a versão editada na véspera da nova.
            $this->repository->closeIntervalBefore($perfil, $novaVigencia, null);
            $this->assertNoOverlap($perfil, $novaVigencia, $encerrada);

            return $this->repository->create($payload);
        });
    }

    /**
     * Exclusão: se referenciada em snapshot, apenas inativa; senão hard delete permitido.
     *
     * @return array{action: 'deactivated'|'deleted', premissa: PremissasViabilidade|null}
     */
    public function delete(PremissasViabilidade $premissa): array
    {
        return DB::transaction(function () use ($premissa): array {
            if ($this->repository->isReferencedInSnapshots((int) $premissa->id)) {
                $updated = $this->repository->deactivate($premissa);

                return [
                    'action' => 'deactivated',
                    'premissa' => $updated,
                ];
            }

            // Impede apagar a única premissa aplicável do perfil sem substituta.
            $perfilValue = $premissa->perfil_financiamento instanceof \BackedEnum
                ? $premissa->perfil_financiamento->value
                : (string) $premissa->getAttribute('perfil_financiamento');
            $perfil = (string) $perfilValue;
            $ativa = $this->repository->findActiveForPerfilAt($perfil);
            if ($ativa !== null && $ativa->id === $premissa->id) {
                throw new PremissasViabilidadeException(
                    'Não é possível excluir a única premissa vigente do perfil. Cadastre outra versão antes.',
                    'PREMISSAS_ONLY_ACTIVE',
                );
            }

            $this->repository->delete($premissa);

            return [
                'action' => 'deleted',
                'premissa' => null,
            ];
        });
    }

    private function resolveNovaVigencia(?string $vigenteEm): string
    {
        if (is_string($vigenteEm) && $vigenteEm !== '') {
            return Carbon::parse($vigenteEm)->toDateString();
        }

        return now()->toDateString();
    }

    private function normalizeEncerrada(mixed $encerradaEm, string $vigenteEm): ?string
    {
        if (! is_string($encerradaEm) || $encerradaEm === '') {
            return null;
        }

        $fim = Carbon::parse($encerradaEm)->toDateString();
        if ($fim < $vigenteEm) {
            throw new PremissasViabilidadeException(
                'A data de encerramento deve ser igual ou posterior à vigência inicial.',
                'PREMISSAS_INVALID_INTERVAL',
            );
        }

        return $fim;
    }

    private function assertNoOverlap(
        string $perfil,
        string $vigenteEm,
        ?string $encerradaEm,
        ?int $exceptId = null
    ): void {
        // Após closeIntervalBefore no mesmo request a sobreposição residual seria bug.
        // Validamos antes do close para payloads que tentam forçar intervalo cruzado.
        $overlaps = $this->repository->findOverlapping($perfil, $vigenteEm, $encerradaEm, $exceptId);

        // Filtra as que serão encerradas exatamente na véspera (permitido).
        $vespera = Carbon::parse($vigenteEm)->subDay()->toDateString();
        $reais = $overlaps->filter(static function (PremissasViabilidade $item) use ($vespera, $vigenteEm): bool {
            $inicio = $item->vigente_em?->toDateString() ?? '0001-01-01';
            $fim = $item->encerrada_em?->toDateString();

            // Se a existente termina na véspera, não é sobreposição real.
            if ($fim !== null && $fim <= $vespera) {
                return false;
            }

            // Se a existente começa na mesma data ou depois e não vamos fechá-la, conflita.
            if ($inicio >= $vigenteEm) {
                return true;
            }

            // Existente aberta que cruza a nova vigência.
            return $fim === null || $fim >= $vigenteEm;
        });

        if ($reais->isNotEmpty()) {
            throw new PremissasViabilidadeException(
                'Já existe premissa ativa com vigência sobreposta para este perfil.',
                'PREMISSAS_OVERLAP',
                [
                    'perfil_financiamento' => $perfil,
                    'vigente_em' => $vigenteEm,
                    'encerrada_em' => $encerradaEm,
                    'conflicting_ids' => $reais->pluck('id')->values()->all(),
                ],
                Response::HTTP_CONFLICT,
            );
        }
    }
}
