<?php

namespace App\Services\Tenant\Viabilidade\v1;

use App\Enums\WorkflowStatus;
use App\Events\Tenant\ViabilidadeDecided;
use App\Events\Tenant\ViabilidadeSubmitted;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use App\Repositories\Tenant\ViabilidadeRepository;
use App\Services\Tenant\LandWorkflowService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ViabilidadeService
{
    /**
     * @var array<string, string>
     */
    private const SNAPSHOT_FORM_ATTRIBUTE_MAP = [
        'terreno_id' => 'terreno_id',
        'prazo_obra' => 'prazo_obra',
        'prazo_lancamento' => 'prazo_lancamento',
        'prazo_incorporacao' => 'prazo_incorporacao',
        'meses_entrega' => 'meses_entrega',
        'meses_pos_obra' => 'meses_pos_obra',
        'parceria_vgv' => 'parceria_vgv',
        'compra_terreno' => 'compra_terreno',
        'infra_nao_incidente' => 'infra_nao_incidente',
        'porcentagem_lote_proprietario' => 'porcentagem_lote_proprietario',
        'pis_cofins' => 'pis_cofins',
        'iss' => 'iss',
        'outros_impostos' => 'outros_impostos',
        'comissao' => 'comissao',
        'incorporacao' => 'incorporacao',
        'incorp_ri' => 'incorporacao_ri',
        'incorp_entrega' => 'incorporacao_entrega',
        'incorp_ate_lancamento' => 'incorporacao_ate_lancamento',
        'obra_ate_lancamento' => 'obra_ate_lancamento',
        'area_comum' => 'area_comum',
        'contrapartidas' => 'contrapartidas',
        'canteiro_mensal' => 'canteiro_mensal',
        'mo_administrativa' => 'mo_administrativa',
        'seguros' => 'seguros',
        'assistencia_tecnica' => 'assistencia_tecnica',
        'despesas_comerciais' => 'despesas_comerciais',
        'stand_vendas' => 'stand_vendas',
        'mobilia_decoracao' => 'mobilia_decoracao',
        'gastos_mensais_stand' => 'gastos_mensais_stand',
        'comissao_house_percentual' => 'comissao_house_percentual',
        'comissao_imobiliarias_percentual' => 'comissao_imobiliarias_percentual',
        'percentual_vendas_house' => 'percentual_vendas_house',
        'construcao_stand_meses_antes_lancamento' => 'construcao_stand_meses_antes_lancamento',
        'ajuda_custo_gerente' => 'ajuda_custo_gerente',
        'ajuda_custo_gerente_regional' => 'ajuda_custo_gerente_regional',
        'reembolso_logistica' => 'reembolso_logistica',
        'bonus_cca' => 'bonus_cca',
        'bonus_gerente' => 'bonus_gerente',
        'bonus_gerente_regional' => 'bonus_gerente_regional',
        'bonus_credito' => 'bonus_credito',
        'bonus_gestor_comercial' => 'bonus_gestor_comercial',
        'bonus_equipe_comercial' => 'bonus_equipe_comercial',
        'pagamento_comissao_venda' => 'pagamento_comissao_venda',
        'pagamento_comissao_desligamento' => 'pagamento_comissao_desligamento',
        'parcelamento_comissao_meses' => 'parcelamento_comissao_meses',
        'parcelamento_comissao_terreno' => 'parcelamento_comissao_terreno',
        'marketing' => 'marketing',
        'marketing_lancamento' => 'marketing_lancamento',
        'marketing_inicio_antes_lancamento' => 'marketing_inicio_antes_lancamento',
        'itbi_iptu' => 'itbi_iptu',
        'registro' => 'registro',
        'custo_contratacao_cef' => 'custo_contratacao_cef',
        'custo_medicao_cef' => 'custo_medicao_cef',
        'contratos_cef' => 'contratos_cef',
        'produtos_cef' => 'produtos_cef',
        'outras_despesas_financeiras' => 'outras_despesas_financeiras',
        'despesas_onerosas_bancos' => 'despesas_onerosas_bancos',
        'taxa_juros_pj' => 'taxa_juros_pj',
        'carencia_pj_meses' => 'carencia_pj_meses',
        'amortizacao_pj_parcelas' => 'amortizacao_pj_parcelas',
        'percentual_antecipacao_pj' => 'percentual_antecipacao_pj',
        'aporte_adicional_mensal' => 'aporte_adicional_mensal',
        'devolucao_aporte_percentual' => 'devolucao_aporte_percentual',
        'distribuicao_lucros_percentual_obra' => 'distribuicao_lucros_percentual_obra',
        'taxa_exposicao_aplicada' => 'taxa_exposicao_aplicada',
        'inadimplencia' => 'inadimplencia',
        'atraso_meses' => 'atraso_meses',
        'taxa_perda' => 'taxa_perda',
        'perfil_financiamento' => 'perfil_financiamento',
    ];

    /**
     * @var list<string>|null
     */
    private static ?array $viabilidadeColumns = null;

    public function __construct(
        private readonly ViabilidadeUnificadoService $unificadoService,
        private readonly LandWorkflowService $workflowService,
        private readonly ViabilidadeRepository $repository,
    ) {}

    public function findOrFail(int|string $id): Viabilidade
    {
        return $this->repository->findOrFail($id);
    }

    public function findWithTrashedOrFail(int|string $id): Viabilidade
    {
        return $this->repository->findWithTrashedOrFail($id);
    }

    /**
     * Listar viabilidades por terreno
     */
    public function listarViabilidadesPorTerreno(int $terrenoId): EloquentCollection
    {
        return $this->repository->listByTerreno($terrenoId);
    }

    /**
     * Buscar viabilidade atual (mais recente) de um terreno
     */
    public function buscarViabilidadeAtual(int $terrenoId): ?Viabilidade
    {
        return $this->repository->latestByTerreno($terrenoId);
    }

    /**
     * Comparar duas viabilidades
     */
    public function compararViabilidades(int $id1, int $id2): array
    {
        $v1 = $this->buscarViabilidadeComDre($id1);
        $v2 = $this->buscarViabilidadeComDre($id2);

        return [
            'viabilidade_1' => $v1,
            'viabilidade_2' => $v2,
        ];
    }

    /**
     * Criar nova viabilidade e gerar DRE automaticamente
     */
    public function criarViabilidadeComDre(array $dados, ?User $actor = null): array
    {
        return DB::transaction(function () use ($dados, $actor) {
            $actor ??= Auth::user();
            $this->validarDados($dados);
            $terreno = $this->repository->findTerrenoOrFail($dados['terreno_id']);
            $nextVersion = $this->repository->nextVersionForTerreno((int) $dados['terreno_id']);
            $payload = $this->prepararPayloadPersistencia($dados, null, $actor);

            $this->repository->clearCurrentForTerreno((int) $dados['terreno_id']);

            $viabilidade = $this->repository->create([
                ...$payload,
                'version' => $nextVersion,
                'is_current' => true,
                'status' => 'rascunho',
                'approval_status' => 'pendente',
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $dreResultados = $this->unificadoService->gerarFluxoMensal(
                $dados['terreno_id'],
                $viabilidade->id,
                $dados['produtos'] ?? null
            );

            $viabilidade = $this->repository->update($viabilidade, [
                'resultados_dre' => $dreResultados,
            ]);

            $this->advanceWorkflowForNewViability(
                $terreno,
                $viabilidade->version,
            );

            return [
                'viabilidade' => $this->repository->loadDefaultRelations($viabilidade),
                'dre_resultados' => $dreResultados,
            ];
        });
    }

    /**
     * Atualizar viabilidade e recalcular DRE
     */
    public function atualizarViabilidadeComDre(Viabilidade|int|string $viabilidade, array $dados, ?User $actor = null): array
    {
        return DB::transaction(function () use ($viabilidade, $dados, $actor) {
            $actor ??= Auth::user();
            $viabilidade = $viabilidade instanceof Viabilidade ? $viabilidade : $this->repository->findOrFail($viabilidade);
            $viabilidade->loadMissing('updatedBy');
            $payload = $this->prepararPayloadPersistencia($dados, $viabilidade, $actor);

            // Se houver validação específica de update, chamar aqui
            // $this->validarDados($dados); // Opcional, dependendo da regra

            $viabilidade = $this->repository->update($viabilidade, [
                ...$payload,
                'updated_by' => $actor?->id,
            ]);

            $dreResultados = $this->unificadoService->gerarFluxoMensal(
                $viabilidade->terreno_id,
                $viabilidade->id,
                $dados['produtos'] ?? null
            );

            $viabilidade = $this->repository->update($viabilidade, [
                'resultados_dre' => $dreResultados,
            ]);

            return [
                'viabilidade' => $this->repository->loadDefaultRelations($viabilidade),
                'dre_resultados' => $dreResultados,
            ];
        });
    }

    /**
     * Buscar viabilidade com DRE por ID
     */
    public function buscarViabilidadeComDre(Viabilidade|int|string $viabilidade): array
    {
        $viabilidade = $viabilidade instanceof Viabilidade ? $viabilidade : $this->repository->findOrFail($viabilidade);
        $viabilidade = $this->repository->loadDreRelations($viabilidade);

        $dreResultados = $viabilidade->resultados_dre;

        if ($this->precisaRecalcularDre($dreResultados)) {
            $dreResultados = $this->recalcularDre($viabilidade)['dre_resultados'];
        }

        return [
            'viabilidade' => $this->repository->loadDreRelations($viabilidade),
            'dre_resultados' => $dreResultados,
        ];
    }

    private function precisaRecalcularDre(mixed $dreResultados): bool
    {
        if (empty($dreResultados)) {
            return true;
        }

        if (! isset($dreResultados['indicadores']) || ! isset($dreResultados['totais'])) {
            return true;
        }

        $fluxo = $dreResultados['fluxo_mensal'] ?? [];
        $primeiroMes = ! empty($fluxo) ? reset($fluxo) : null;

        if (! $primeiroMes) {
            return true;
        }

        if (! isset($primeiroMes['receitas']['recursos_proprios'])) {
            return true;
        }

        // Compatibilidade: versões antigas persistiram POC zerado por divergência
        // de chaves em despesas. Quando houver receita no DRE e POC zerado, força recálculo.
        $receitaTotalVendas = (float) ($dreResultados['dre_itens']['receita_total_vendas'] ?? 0.0);
        $receitaCaixaTotal = (float) ($dreResultados['dre_caixa']['receita_total'] ?? 0.0);
        $pocReceita = (float) ($dreResultados['dre_contabil_poc']['receita_reconhecida_poc'] ?? 0.0);
        $pocBlocosReceita = (float) ($dreResultados['dre_contabil_poc_mensal_blocos']['resumo']['receita_reconhecida_poc_total'] ?? 0.0);

        if (($receitaTotalVendas > 0 || $receitaCaixaTotal > 0) && $pocReceita === 0.0 && $pocBlocosReceita === 0.0) {
            return true;
        }

        return false;
    }

    /**
     * Listar todas as viabilidades com paginação e filtros
     */
    public function listarTodasViabilidades(array $filtros = []): LengthAwarePaginator
    {
        return $this->repository->paginate($filtros);
    }

    /**
     * Validar dados de viabilidade
     */
    public function validarDados(array $dados): array
    {
        if (empty($dados['terreno_id'])) {
            throw new Exception('ID do terreno é obrigatório');
        }

        if (! $this->repository->terrenoExists($dados['terreno_id'])) {
            throw new Exception('Terreno não encontrado');
        }

        // Validação de numéricos pode ser simplificada com filter_var ou validator do Laravel
        // Mantendo lógica original mas simplificada e centralizada
        $camposNumericos = Viabilidade::CAMPOS_FINANCEIROS;

        foreach ($camposNumericos as $campo) {
            if (isset($dados[$campo]) && ! is_numeric($dados[$campo])) {
                throw new Exception("Campo {$campo} deve ser numérico");
            }
        }

        if (isset($dados['prazo_obra'])) {
            $prazosValidos = ['18', '24', '36', '48', '60'];
            if (! in_array((string) $dados['prazo_obra'], $prazosValidos)) {
                throw new Exception('Prazo de obra deve ser: 18, 24, 36, 48 ou 60 meses');
            }
        }

        return $dados;
    }

    /**
     * Duplicar viabilidade (para criar nova versão)
     */
    public function duplicarViabilidade(int $viabilidadeId, ?User $actor = null): Viabilidade
    {
        $actor ??= Auth::user();
        $viabilidadeOriginal = $this->repository->findOrFail($viabilidadeId);
        $nextVersion = $this->repository->nextVersionForTerreno($viabilidadeOriginal->terreno_id);

        $dadosNova = $viabilidadeOriginal->toArray();
        $dadosNova['created_by'] = $actor?->id;
        $dadosNova['updated_by'] = $actor?->id;
        $dadosNova['resultados_dre'] = null;
        $dadosNova['approval_status'] = 'pendente';
        $dadosNova['approval_requested_at'] = null;
        $dadosNova['approval_decided_at'] = null;
        $dadosNova['approval_decided_by'] = null;
        $dadosNova['approval_notes'] = null;
        $dadosNova['submitted_at'] = null;
        $dadosNova['locked_at'] = null;
        $dadosNova['status'] = 'rascunho';
        $dadosNova['version'] = $nextVersion;
        $dadosNova['is_current'] = true;

        $this->repository->clearCurrentForTerreno($viabilidadeOriginal->terreno_id);

        // Remove campos gerados automaticamente
        unset($dadosNova['id'], $dadosNova['created_at'], $dadosNova['updated_at'], $dadosNova['deleted_at']);

        $novaViabilidade = $this->repository->create($dadosNova);
        $this->repository->copySections($viabilidadeOriginal, $novaViabilidade);

        return $this->repository->loadDefaultRelations($novaViabilidade);
    }

    /**
     * Excluir viabilidade (soft delete)
     */
    public function excluirViabilidade(int $viabilidadeId): bool
    {
        $viabilidade = $this->repository->findOrFail($viabilidadeId);

        return $this->repository->delete($viabilidade);
    }

    /**
     * Recalcular DRE de uma viabilidade existente
     */
    public function recalcularDre(Viabilidade|int|string $viabilidade, ?User $actor = null): array
    {
        return DB::transaction(function () use ($viabilidade, $actor) {
            $actor ??= Auth::user();
            $viabilidade = $viabilidade instanceof Viabilidade ? $viabilidade : $this->repository->findOrFail($viabilidade);

            $dreResultados = $this->unificadoService->gerarFluxoMensal(
                $viabilidade->terreno_id,
                $viabilidade->id
            );

            $viabilidade = $this->repository->update($viabilidade, [
                'resultados_dre' => $dreResultados,
                'updated_by' => $actor?->id,
                'updated_at' => now(),
            ]);

            return [
                'viabilidade' => $this->repository->loadDefaultRelations($viabilidade),
                'dre_resultados' => $dreResultados,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function prepararPayloadPersistencia(
        array $dados,
        ?Viabilidade $viabilidade = null,
        ?User $actor = null
    ): array
    {
        $payload = collect($dados)
            ->except(['produtos', 'medicao_contratacao'])
            ->toArray();

        $aliasMap = [
            'incorp_ri' => 'incorporacao_ri',
            'incorp_entrega' => 'incorporacao_entrega',
            'incorp_ate_lancamento' => 'incorporacao_ate_lancamento',
        ];

        foreach ($aliasMap as $source => $target) {
            if (array_key_exists($source, $payload)) {
                $payload[$target] = $payload[$source];
                unset($payload[$source]);
            }
        }

        $snapshotAtual = $viabilidade?->getAttribute('premissas_snapshot');
        $snapshotBase = is_array($snapshotAtual) ? $snapshotAtual : [];
        $formValues = $viabilidade instanceof Viabilidade
            ? $this->snapshotFromCurrentViabilidade($viabilidade)
            : $dados;
        $historicoAtual = is_array($snapshotBase['historico'] ?? null)
            ? $snapshotBase['historico']
            : [];
        $novoHistorico = $historicoAtual;

        if ($viabilidade instanceof Viabilidade) {
            $afterFormValues = $this->mergeSnapshotFormValues($formValues, $dados);
            $novoHistorico[] = [
                'alterado_em' => now()->toIso8601String(),
                'alterado_por_user' => $actor ? [
                    'id' => $actor->id,
                    'name' => $actor->name,
                ] : null,
                'before_form_values' => $formValues,
                'after_form_values' => $afterFormValues,
            ];
        }

        $payload['premissas_snapshot'] = [
            ...$snapshotBase,
            'form_values' => $formValues,
            'historico' => $novoHistorico,
            'alterado_em' => now()->toIso8601String(),
            'alterado_por_user' => $actor ? [
                'id' => $actor->id,
                'name' => $actor->name,
            ] : ($snapshotBase['alterado_por_user'] ?? null),
        ];

        if ($viabilidade instanceof Viabilidade) {
            $payload['premissas_snapshot']['referencia_atualizada_em'] =
                $viabilidade->updated_at?->toIso8601String();
            $payload['premissas_snapshot']['referencia_atualizada_por_user'] =
                $viabilidade->relationLoaded('updatedBy') && $viabilidade->updatedBy
                    ? [
                        'id' => $viabilidade->updatedBy->id,
                        'name' => $viabilidade->updatedBy->name,
                    ]
                    : null;
        }

        $columns = $this->viabilidadeColumns();

        return array_filter(
            $payload,
            static fn (string $key): bool => in_array($key, $columns, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFromCurrentViabilidade(Viabilidade $viabilidade): array
    {
        $snapshotAtual = $viabilidade->getAttribute('premissas_snapshot');
        $snapshotBase = is_array($snapshotAtual) ? $snapshotAtual : [];
        $formValuesAtuais = is_array($snapshotBase['form_values'] ?? null)
            ? $snapshotBase['form_values']
            : [];

        $formValues = $formValuesAtuais;

        foreach (self::SNAPSHOT_FORM_ATTRIBUTE_MAP as $formKey => $attribute) {
            $value = $viabilidade->getAttribute($attribute);

            if ($value !== null) {
                $formValues[$formKey] = $value;
            }
        }

        return $formValues;
    }

    /**
     * @param  array<string, mixed>  $beforeFormValues
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function mergeSnapshotFormValues(array $beforeFormValues, array $dados): array
    {
        $afterFormValues = $beforeFormValues;

        foreach ($dados as $key => $value) {
            if ($value === null) {
                $afterFormValues[$key] = null;
                continue;
            }

            $afterFormValues[$key] = $value;
        }

        return $afterFormValues;
    }

    /**
     * @return list<string>
     */
    private function viabilidadeColumns(): array
    {
        if (self::$viabilidadeColumns === null) {
            /** @var list<string> $columns */
            $columns = Schema::getColumnListing('viabilidades');
            self::$viabilidadeColumns = $columns;
        }

        return self::$viabilidadeColumns;
    }

    public function registrarAprovacao(Viabilidade $viabilidade, string $decision, ?string $comments = null, ?User $actor = null): void
    {
        $actor ??= Auth::user();

        $this->repository->createApproval($viabilidade, $actor?->id, $decision, $comments);
    }

    public function ativar(int|string $viabilidadeId, ?User $actor = null): Viabilidade
    {
        $actor ??= Auth::user();
        $viabilidade = $this->repository->findOrFail($viabilidadeId);

        return $this->repository->loadDefaultRelations(
            $this->repository->update($viabilidade, [
                'status' => 'ativo',
                'updated_by' => $actor?->id,
            ])
        );
    }

    public function solicitarAprovacao(int|string $viabilidadeId, ?string $approvalNotes, ?User $actor = null): Viabilidade
    {
        $actor ??= Auth::user();
        $viabilidade = $this->repository->loadDefaultRelations(
            $this->repository->findOrFail($viabilidadeId)
        );

        $viabilidade = $this->repository->update($viabilidade, [
            'approval_status' => 'em_aprovacao',
            'approval_requested_at' => now(),
            'submitted_at' => now(),
            'approval_decided_at' => null,
            'approval_decided_by' => null,
            'approval_notes' => $approvalNotes,
            'updated_by' => $actor?->id,
        ]);

        $terreno = $viabilidade->terreno ?? $this->repository->findTerrenoOrFail($viabilidade->terreno_id);

        $this->workflowService->transition(
            $terreno,
            WorkflowStatus::AGUARDANDO_VIABILIDADE->value,
            $actor,
            'viability_submitted',
            $approvalNotes,
        );

        ViabilidadeSubmitted::dispatch($viabilidade, $terreno, $actor);

        return $this->repository->loadDefaultRelations($viabilidade);
    }

    public function decidirAprovacao(int|string $viabilidadeId, string $decision, ?string $approvalNotes, ?User $actor = null): Viabilidade
    {
        $actor ??= Auth::user();
        $viabilidade = $this->repository->loadDefaultRelations(
            $this->repository->findOrFail($viabilidadeId)
        );

        $approvalStatus = $viabilidade->approval_status ?? ($viabilidade->status === 'ativo' ? 'aprovada' : 'pendente');

        if ($approvalStatus !== 'em_aprovacao') {
            throw ValidationException::withMessages([
                'approval_notes' => ['A viabilidade precisa estar em aprovação antes desta decisão.'],
            ]);
        }

        $payload = [
            'approval_status' => $decision,
            'approval_decided_at' => now(),
            'approval_decided_by' => $actor?->id,
            'approval_notes' => $approvalNotes ?? $viabilidade->approval_notes,
            'updated_by' => $actor?->id,
        ];

        if ($decision === 'aprovada') {
            $payload['status'] = 'ativo';
            $payload['locked_at'] = now();
        } else {
            $payload['status'] = 'rascunho';
            $payload['locked_at'] = null;
        }

        $viabilidade = $this->repository->update($viabilidade, $payload);
        $this->registrarAprovacao($viabilidade, $decision, $approvalNotes, $actor);

        $terreno = $viabilidade->terreno ?? $this->repository->findTerrenoOrFail($viabilidade->terreno_id);

        $this->workflowService->transition(
            $terreno,
            $decision === 'aprovada' ? WorkflowStatus::VIABILIDADE_APROVADA->value : WorkflowStatus::EM_ANALISE->value,
            $actor,
            'viability_decided',
            $approvalNotes,
        );

        ViabilidadeDecided::dispatch($viabilidade, $terreno, $decision, $actor);

        return $this->repository->loadDefaultRelations($viabilidade);
    }

    public function restore(int|string $viabilidadeId): Viabilidade
    {
        $viabilidade = $this->repository->findWithTrashedOrFail($viabilidadeId);

        return $this->repository->loadDefaultRelations(
            $this->repository->restore($viabilidade)
        );
    }

    /**
     * @return Collection<int, array{id: int, label: string, terreno_id: int}>
     */
    public function forSelect(?int $terrenoId = null): Collection
    {
        return $this->repository->forSelect($terrenoId)->map(function (Viabilidade $viabilidade): array {
            $data = $viabilidade->created_at?->format('d/m/Y H:i') ?? '';

            return [
                'id' => $viabilidade->id,
                'label' => "Viabilidade #{$viabilidade->id} - {$viabilidade->terreno?->nome} ({$data})",
                'terreno_id' => $viabilidade->terreno_id,
            ];
        })->values();
    }

    /**
     * @return array{viabilidade_1: array<string, mixed>, viabilidade_2: array<string, mixed>}
     */
    public function compareByIds(int $id1, int $id2): array
    {
        return [
            'viabilidade_1' => $this->buscarViabilidadeComDre($id1),
            'viabilidade_2' => $this->buscarViabilidadeComDre($id2),
        ];
    }

    /**
     * @return array{viabilidade: Viabilidade, dre: array<string, mixed>, dataGeracao: string}
     */
    public function exportData(int|string $viabilidadeId): array
    {
        $resultado = $this->buscarViabilidadeComDre($viabilidadeId);
        /** @var Viabilidade $viabilidade */
        $viabilidade = $resultado['viabilidade'];
        $dre = $resultado['dre_resultados'];

        if (! $dre || ! isset($dre['totais'])) {
            $resultado = $this->recalcularDre($viabilidade);
            $dre = $resultado['dre_resultados'];
        }

        if (! $dre) {
            throw new Exception('Não foi possível carregar ou gerar os dados do DRE para esta viabilidade.');
        }

        return [
            'viabilidade' => $viabilidade,
            'dre' => $dre,
            'dataGeracao' => now()->format('d/m/Y H:i'),
        ];
    }

    protected function advanceWorkflowForNewViability(Terreno $terreno, int $version): void
    {
        $user = Auth::user();
        $reasonNotes = "Viabilidade versão {$version} criada.";

        $currentStatus = $terreno->workflow_status_code ?? null;

        if ($currentStatus === WorkflowStatus::AGUARDANDO_VIABILIDADE->value) {
            return;
        }

        $statusesAposViabilidade = [
            WorkflowStatus::VIABILIDADE_APROVADA->value,
            WorkflowStatus::AGUARDANDO_COMITE->value,
            WorkflowStatus::NEGOCIACAO_MINUTA->value,
            WorkflowStatus::CONTRATO_ASSINADO->value,
            WorkflowStatus::LEGALIZANDO->value,
            WorkflowStatus::LEGALIZADO_FINALIZADO->value,
        ];

        if (in_array($currentStatus, $statusesAposViabilidade, true)) {
            return;
        }

        $this->workflowService->transition(
            $terreno,
            WorkflowStatus::AGUARDANDO_VIABILIDADE->value,
            $user,
            'viability_created',
            $reasonNotes,
        );
    }
}
