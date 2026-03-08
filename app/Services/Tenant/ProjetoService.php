<?php

namespace App\Services\Tenant;

use App\Http\Resources\Tenant\LegalizacaoResource;
use App\Http\Resources\Tenant\ProjetoResource;
use App\Http\Resources\Tenant\TerrenoResource;
use App\Http\Resources\Tenant\ViabilidadeResource;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoStatus;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjetoService
{
    public function listar(array $filters = []): LengthAwarePaginator
    {
        $query = Projeto::query()
            ->with([
                'responsavel',
                'terreno.status',
                'terreno.viabilidadeAtual.approvalDecidedBy',
                'terreno.legalizacao',
                'prontoParaRegistroPor',
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('nome', 'like', "%{$search}%")
                    ->orWhereHas('terreno', function (Builder $terrenoQuery) use ($search) {
                        $terrenoQuery
                            ->where('nome', 'like', "%{$search}%")
                            ->orWhere('endereco', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 10);

        $paginator->getCollection()->each(function (Projeto $projeto) {
            $this->refreshStatus($projeto);
        });

        return $paginator;
    }

    public function listarTerrenosElegiveis(array $filters = []): LengthAwarePaginator
    {
        $query = Terreno::query()
            ->select('terrenos.*')
            ->join('terreno_status as projeto_terreno_status', 'projeto_terreno_status.id', '=', 'terrenos.status_id')
            ->with(['status', 'cidade', 'responsavel', 'proprietarios', 'informacoes'])
            ->whereRaw('LOWER(TRIM(projeto_terreno_status.nome)) LIKE ?', ['%contrato assinado%']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('nome', 'like', "%{$search}%")
                    ->orWhere('endereco', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 10);
    }

    public function criar(array $data): Projeto
    {
        return DB::transaction(function () use ($data) {
            $terreno = $this->validarTerrenoElegivel($data['terreno_id']);
            $this->validarProjetoAtivoUnico($terreno->id);

            $projeto = Projeto::create([
                'nome' => $data['nome'],
                'terreno_id' => $terreno->id,
                'responsavel_id' => $data['responsavel_id'] ?? null,
                'status' => $this->resolveStatusFromRelations($terreno),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return $this->buscar($projeto->id);
        });
    }

    public function buscar(int $id): Projeto
    {
        $projeto = Projeto::query()
            ->with([
                'responsavel',
                'createdBy',
                'updatedBy',
                'prontoParaRegistroPor',
                'terreno.status',
                'terreno.cidade',
                'terreno.responsavel',
                'terreno.proprietarios',
                'terreno.informacoes',
                'terreno.viabilidadeAtual.createdBy',
                'terreno.viabilidadeAtual.approvalDecidedBy',
                'terreno.legalizacao.terreno',
                'terreno.legalizacao.responsavel',
                'terreno.legalizacao.etapas',
            ])
            ->findOrFail($id);

        $this->refreshStatus($projeto);

        return $projeto->fresh([
            'responsavel',
            'createdBy',
            'updatedBy',
            'prontoParaRegistroPor',
            'terreno.status',
            'terreno.cidade',
            'terreno.responsavel',
            'terreno.proprietarios',
            'terreno.informacoes',
            'terreno.viabilidadeAtual.createdBy',
            'terreno.viabilidadeAtual.approvalDecidedBy',
            'terreno.legalizacao.terreno',
            'terreno.legalizacao.responsavel',
            'terreno.legalizacao.etapas',
        ]);
    }

    public function atualizar(Projeto $projeto, array $data): Projeto
    {
        $payload = [
            'updated_by' => Auth::id(),
        ];

        if (array_key_exists('nome', $data)) {
            $payload['nome'] = $data['nome'];
        }

        if (array_key_exists('responsavel_id', $data)) {
            $payload['responsavel_id'] = $data['responsavel_id'];
        }

        if (array_key_exists('status', $data) && $projeto->status !== Projeto::STATUS_PRONTO_PARA_REGISTRO) {
            $payload['status'] = $data['status'];
        }

        $projeto->update($payload);

        $this->refreshStatus($projeto);

        return $this->buscar($projeto->id);
    }

    public function cancelar(Projeto $projeto): Projeto
    {
        $projeto->update([
            'status' => Projeto::STATUS_CANCELADO,
            'updated_by' => Auth::id(),
        ]);

        return $this->buscar($projeto->id);
    }

    public function marcarProntoParaRegistro(Projeto $projeto): Projeto
    {
        $projeto = $this->buscar($projeto->id);
        $legalizacao = $projeto->terreno?->legalizacao;

        if (!$legalizacao || $legalizacao->status !== 'concluido') {
            throw new \RuntimeException('A legalização precisa estar concluída antes de marcar o projeto como pronto para registro.');
        }

        return DB::transaction(function () use ($projeto) {
            $statusRegistro = TerrenoStatus::query()
                ->whereRaw('LOWER(nome) = ?', ['registro'])
                ->first();

            if (!$statusRegistro) {
                throw new \RuntimeException('O status "Registro" não está cadastrado.');
            }

            $projeto->update([
                'status' => Projeto::STATUS_PRONTO_PARA_REGISTRO,
                'pronto_para_registro_em' => now(),
                'pronto_para_registro_por' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $projeto->terreno()->update([
                'status_id' => $statusRegistro->id,
                'updated_by' => Auth::id(),
            ]);

            return $this->buscar($projeto->id);
        });
    }

    public function workflow(Projeto $projeto, ?User $user): array
    {
        $terreno = $projeto->terreno;
        $viabilidade = $terreno?->viabilidadeAtual;
        $legalizacao = $terreno?->legalizacao;
        $viabilidadeApproved = $this->isViabilidadeAprovada($viabilidade);
        $canApprove = $user ? $this->canApproveViabilidade($user) : false;
        $projectIsActive = !in_array($projeto->status, [
            Projeto::STATUS_PRONTO_PARA_REGISTRO,
            Projeto::STATUS_CANCELADO,
        ], true);

        $canStartLegalizacao = $projectIsActive && $viabilidadeApproved && !$legalizacao;
        $canMarkReady = $legalizacao?->status === 'concluido'
            && $projectIsActive;

        return [
            'can_request_viability_approval' => $projectIsActive
                && (bool) $viabilidade
                && !in_array(($viabilidade->approval_status ?? 'pendente'), ['em_aprovacao', 'aprovada'], true),
            'can_approve_viability' => $projectIsActive
                && (bool) $viabilidade
                && ($viabilidade->approval_status ?? 'pendente') === 'em_aprovacao'
                && $canApprove,
            'can_start_legalizacao' => $canStartLegalizacao,
            'can_mark_ready_for_registry' => $canMarkReady,
            'next_step' => $this->nextStep($projeto, $viabilidade, $legalizacao),
        ];
    }

    public function workspacePayload(Projeto $projeto, ?User $user): array
    {
        $terreno = $projeto->terreno;

        return [
            'projeto' => new ProjetoResource($projeto),
            'terreno' => $terreno ? new TerrenoResource($terreno) : null,
            'viabilidade_atual' => $terreno?->viabilidadeAtual ? new ViabilidadeResource($terreno->viabilidadeAtual) : null,
            'legalizacao' => $terreno?->legalizacao ? new LegalizacaoResource($terreno->legalizacao) : null,
            'workflow' => $this->workflow($projeto, $user),
        ];
    }

    public function refreshStatus(Projeto $projeto): Projeto
    {
        if (in_array($projeto->status, [
            Projeto::STATUS_PRONTO_PARA_REGISTRO,
            Projeto::STATUS_CANCELADO,
        ], true)) {
            return $projeto;
        }

        $projeto->loadMissing([
            'terreno.viabilidadeAtual',
            'terreno.legalizacao',
        ]);

        $resolvedStatus = $this->resolveStatusFromRelations($projeto->terreno);

        if ($projeto->status !== $resolvedStatus) {
            $projeto->forceFill([
                'status' => $resolvedStatus,
                'updated_by' => Auth::id() ?? $projeto->updated_by,
            ])->save();
        }

        return $projeto;
    }

    protected function validarTerrenoElegivel(int $terrenoId): Terreno
    {
        $terreno = Terreno::query()
            ->with(['status', 'viabilidadeAtual', 'legalizacao'])
            ->findOrFail($terrenoId);

        $isContratoAssinado = Terreno::query()
            ->join('terreno_status as projeto_terreno_status', 'projeto_terreno_status.id', '=', 'terrenos.status_id')
            ->where('terrenos.id', $terreno->id)
            ->whereRaw('LOWER(TRIM(projeto_terreno_status.nome)) LIKE ?', ['%contrato assinado%'])
            ->exists();

        if (!$isContratoAssinado) {
            throw new \RuntimeException('Somente terrenos com status "Contrato Assinado" podem iniciar um projeto.');
        }

        $this->validarProjetoAtivoUnico($terreno->id);

        return $terreno;
    }

    protected function validarProjetoAtivoUnico(int $terrenoId): void
    {
        $exists = Projeto::query()
            ->where('terreno_id', $terrenoId)
            ->whereIn('status', [
                Projeto::STATUS_EM_VIABILIDADE,
                Projeto::STATUS_EM_LEGALIZACAO,
            ])
            ->exists();

        if ($exists) {
            throw new \RuntimeException('Já existe um projeto ativo para este terreno.');
        }
    }

    protected function resolveStatusFromRelations(?Terreno $terreno): string
    {
        $viabilidade = $terreno?->viabilidadeAtual;
        $legalizacao = $terreno?->legalizacao;

        if ($legalizacao && $legalizacao->status === 'concluido') {
            return Projeto::STATUS_EM_LEGALIZACAO;
        }

        if ($this->isViabilidadeAprovada($viabilidade)) {
            return Projeto::STATUS_EM_LEGALIZACAO;
        }

        return Projeto::STATUS_EM_VIABILIDADE;
    }

    protected function isViabilidadeAprovada(?Viabilidade $viabilidade): bool
    {
        if (!$viabilidade) {
            return false;
        }

        $approvalStatus = $viabilidade->approval_status ?? null;

        return $approvalStatus === 'aprovada' || ($approvalStatus === null && $viabilidade->status === 'ativo');
    }

    protected function canApproveViabilidade(User $user): bool
    {
        return $user->isAdmin() || $user->hasAnyRole(['manager', 'diretor', 'coordenador']);
    }

    protected function nextStep(Projeto $projeto, ?Viabilidade $viabilidade, mixed $legalizacao): string
    {
        if ($projeto->status === Projeto::STATUS_PRONTO_PARA_REGISTRO) {
            return 'Projeto concluído e pronto para registro.';
        }

        if ($projeto->status === Projeto::STATUS_CANCELADO) {
            return 'Projeto cancelado.';
        }

        if (!$viabilidade) {
            return 'Criar viabilidade para o terreno.';
        }

        if (!$this->isViabilidadeAprovada($viabilidade)) {
            $approvalStatus = $viabilidade->approval_status ?? 'pendente';

            return match ($approvalStatus) {
                'em_aprovacao' => 'Aguardar aprovação da viabilidade.',
                'reprovada' => 'Revisar a viabilidade e solicitar nova aprovação.',
                default => 'Solicitar aprovação da viabilidade.',
            };
        }

        if (!$legalizacao) {
            return 'Iniciar o processo de legalização.';
        }

        if ($legalizacao->status !== 'concluido') {
            return 'Acompanhar a legalização até a conclusão.';
        }

        return 'Marcar o projeto como pronto para registro.';
    }
}
