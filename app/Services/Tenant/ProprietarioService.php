<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Proprietario;
use App\Repositories\Contracts\ProprietarioRepositoryInterface;
use App\Repositories\Tenant\PrivacySubjectRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProprietarioService
{
    public function __construct(
        private readonly ProprietarioRepositoryInterface $repository,
        private readonly PrivacySubjectRepository $privacySubjects,
    ) {}

    public function list(int $tenantId, int $perPage, ?int $terrenoId = null): LengthAwarePaginator
    {
        return $this->repository->paginateForTenant($tenantId, $perPage, $terrenoId);
    }

    /**
     * @return Collection<int, Proprietario>
     */
    public function forSelect(): Collection
    {
        return $this->repository->forSelect();
    }

    public function findById(int $id): ?Proprietario
    {
        return $this->repository->findById($id);
    }

    public function findWithRelations(int $id): ?Proprietario
    {
        return $this->repository->findWithRelations($id);
    }

    public function create(array $data): Proprietario
    {
        return $this->repository->create($data);
    }

    public function update(Proprietario $proprietario, array $data): Proprietario
    {
        return $this->repository->update($proprietario, $data);
    }

    public function delete(Proprietario $proprietario): void
    {
        $this->repository->delete($proprietario);
    }

    public function anonymize(Proprietario $proprietario): Proprietario
    {
        $terrenoId = (int) $proprietario->getAttribute('terreno_id');
        $payload = [
            'nome' => 'Titular anonimizado',
            'rg' => null,
            'cpf_cnpj' => null,
            'nascimento' => null,
            'email' => null,
            'telefone' => null,
            'endereco' => null,
            'cep' => null,
            'conjuge' => null,
            'conjuge_rg' => null,
            'conjuge_nascimento' => null,
            'conjuge_cpf_cnpj' => null,
            'observacoes' => null,
            'nacionalidade' => null,
            'profissao' => null,
            'cpf_cnpj_hash' => null,
        ];

        $anonymized = $this->repository->anonymize($proprietario, $payload);

        if ($terrenoId > 0) {
            $this->privacySubjects->deleteOwnerIdentityIntelligence($terrenoId);
        }

        return $anonymized;
    }
}
