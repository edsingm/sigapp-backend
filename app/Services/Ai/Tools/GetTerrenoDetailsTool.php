<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Contrato;
use App\Models\Tenant\Documento;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetTerrenoDetailsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Retorna detalhes de um terreno pelo id. Padrão mode=summary (payload magro). Use mode=full ou flags include_* para contexto completo. Não use para números agregados da carteira (use GetDashboardSummaryTool).';
    }

    public function handle(Request $request): Stringable|string
    {
        $terrenoId = (int) ($request['terreno_id'] ?? $request['id'] ?? 0);
        $mode = strtolower(trim((string) ($request['mode'] ?? 'summary')));
        if (! in_array($mode, ['summary', 'full'], true)) {
            $mode = 'summary';
        }

        if ($terrenoId <= 0) {
            return AiToolResponse::validation('Informe um terreno_id válido.');
        }

        $auth = app(AiToolAuth::class);
        if ($deny = $auth->ensureViewAny(
            Terreno::class,
            'Acesso negado: você não tem permissão para acessar terrenos.'
        )) {
            return $deny;
        }

        $isFull = $mode === 'full';
        $requestedViabilidades = filter_var(
            $request['include_viabilidades'] ?? false,
            FILTER_VALIDATE_BOOL
        );
        $requestedNegociacao = filter_var(
            $request['include_negociacao'] ?? $isFull,
            FILTER_VALIDATE_BOOL
        );
        $requestedContrato = filter_var(
            $request['include_contrato'] ?? $isFull,
            FILTER_VALIDATE_BOOL
        );
        $requestedProjetos = filter_var(
            $request['include_projetos'] ?? $isFull,
            FILTER_VALIDATE_BOOL
        );

        $canViewViabilidades = $auth->canUseFeature('viabilities.enabled')
            && $auth->canViewAny(Viabilidade::class);
        $canViewNegociacao = $auth->canUseFeature('negotiation')
            && $auth->canViewAny(Negociacao::class);
        $canViewContrato = $auth->canUseFeature('negotiation')
            && $auth->canViewAny(Contrato::class);
        $canViewProjetos = $auth->canUseFeature('projects.room')
            && $auth->canViewAny(Projeto::class);
        $canViewDocumentos = $auth->canViewAny(Documento::class);
        $canViewProprietarios = $auth->canViewAny(Proprietario::class);

        $includeNegociacao = $requestedNegociacao && $canViewNegociacao;
        $includeContrato = $requestedContrato && $canViewContrato;
        $includeProjetos = $requestedProjetos && $canViewProjetos;
        $includeViabilidades = $requestedViabilidades && $canViewViabilidades;

        $with = ['cidade:code,city'];
        if ($canViewViabilidades) {
            $with['viabilidadeAtual'] = static function ($query): void {
                $query->select([
                    'viabilidades.id',
                    'viabilidades.terreno_id',
                    'viabilidades.version',
                    'viabilidades.status',
                    'viabilidades.approval_status',
                    'viabilidades.updated_at',
                ]);
            };
        }

        if ($includeNegociacao) {
            $with['negociacaoAtual'] = static function ($query): void {
                $query->select([
                    'negociacoes.id',
                    'negociacoes.terreno_id',
                    'negociacoes.status',
                    'negociacoes.proposal_value',
                    'negociacoes.started_at',
                    'negociacoes.closed_at',
                ]);
            };
        }

        if ($includeContrato) {
            $with['contratoAtual'] = static function ($query): void {
                $query->select([
                    'contratos.id',
                    'contratos.terreno_id',
                    'contratos.contract_type',
                    'contratos.contract_number',
                    'contratos.status',
                    'contratos.signed_at',
                ]);
            };
        }

        if ($includeProjetos) {
            $with[] = 'projetos:id,terreno_id,nome,status,created_at';
        }

        $withCount = ['contatos'];
        if ($canViewDocumentos) {
            $withCount[] = 'documentos';
        }
        if ($canViewProprietarios) {
            $withCount[] = 'proprietarios';
        }
        if ($canViewViabilidades) {
            $withCount[] = 'viabilidades';
        }
        if ($canViewProjetos) {
            $withCount[] = 'projetos';
        }

        $terreno = Terreno::query()
            ->with($with)
            ->withCount($withCount)
            ->select([
                'id',
                'nome',
                'endereco',
                'cep',
                'bairro',
                'cidade_code',
                'estado',
                'distrito',
                'zona',
                'operacao_urbana',
                'area_calculada',
                'valor',
                'workflow_stage',
                'workflow_status_code',
                'workflow_reason_code',
                'workflow_reason_notes',
                'observacoes',
                'data_apresentacao',
                'data_negociacao',
                'data_opcao',
                'data_contrato',
                'data_descarte',
                'updated_at',
            ])
            ->find($terrenoId);

        if (! $terreno) {
            return AiToolResponse::empty("Terreno {$terrenoId} não encontrado.");
        }

        if ($deny = $auth->ensureView(
            $terreno,
            "Acesso negado: você não tem permissão para visualizar o terreno {$terrenoId}."
        )) {
            return $deny;
        }

        $payload = [
            'mode' => $mode,
            'id' => $terreno->id,
            'nome' => $terreno->nome,
            'endereco' => $terreno->endereco,
            'cidade' => $terreno->cidade?->city ?? $terreno->cidade_code,
            'estado' => $terreno->estado,
            'area_calculada' => $terreno->area_calculada,
            'valor' => $terreno->valor,
            'workflow_stage' => $terreno->workflow_stage,
            'workflow_status_code' => $terreno->workflow_status_code,
            'updated_at' => optional($terreno->updated_at)?->toAtomString(),
            'totais' => [
                'contatos' => $terreno->contatos_count,
            ],
        ];

        if ($canViewDocumentos) {
            $payload['totais']['documentos'] = $terreno->documentos_count;
        }
        if ($canViewProprietarios) {
            $payload['totais']['proprietarios'] = $terreno->proprietarios_count;
        }
        if ($canViewViabilidades) {
            $payload['totais']['viabilidades'] = $terreno->viabilidades_count;
            $payload['viabilidade_atual'] = $terreno->viabilidadeAtual ? [
                'id' => $terreno->viabilidadeAtual->id,
                'version' => $terreno->viabilidadeAtual->version,
                'status' => $terreno->viabilidadeAtual->status,
                'approval_status' => $terreno->viabilidadeAtual->approval_status,
                'updated_at' => optional($terreno->viabilidadeAtual->updated_at)?->toAtomString(),
            ] : null;
        }
        if ($canViewProjetos) {
            $payload['totais']['projetos'] = $terreno->projetos_count;
        }

        $restrictedSections = [];
        if ($requestedViabilidades && ! $canViewViabilidades) {
            $restrictedSections[] = 'viabilidades';
        }
        if ($requestedNegociacao && ! $canViewNegociacao) {
            $restrictedSections[] = 'negociacao';
        }
        if ($requestedContrato && ! $canViewContrato) {
            $restrictedSections[] = 'contrato';
        }
        if ($requestedProjetos && ! $canViewProjetos) {
            $restrictedSections[] = 'projetos';
        }
        if ($restrictedSections !== []) {
            $payload['restricted_sections'] = $restrictedSections;
        }

        if ($isFull) {
            $payload['cep'] = $terreno->cep;
            $payload['bairro'] = $terreno->bairro;
            $payload['distrito'] = $terreno->distrito;
            $payload['zona'] = $terreno->zona;
            $payload['operacao_urbana'] = $terreno->operacao_urbana;
            $payload['workflow_reason_code'] = $terreno->workflow_reason_code;
            $payload['workflow_reason_notes'] = $terreno->workflow_reason_notes;
            $payload['observacoes'] = $terreno->observacoes;
            $payload['datas'] = [
                'apresentacao' => optional($terreno->data_apresentacao)?->toDateString(),
                'negociacao' => optional($terreno->data_negociacao)?->toDateString(),
                'opcao' => optional($terreno->data_opcao)?->toDateString(),
                'contrato' => optional($terreno->data_contrato)?->toDateString(),
                'descarte' => optional($terreno->data_descarte)?->toDateString(),
                'updated_at' => optional($terreno->updated_at)?->toAtomString(),
            ];
        }

        if ($includeNegociacao) {
            $payload['negociacao_atual'] = $terreno->negociacaoAtual ? [
                'status' => $terreno->negociacaoAtual->status,
                'proposal_value' => $terreno->negociacaoAtual->proposal_value,
                'started_at' => optional($terreno->negociacaoAtual->started_at)?->toAtomString(),
                'closed_at' => optional($terreno->negociacaoAtual->closed_at)?->toAtomString(),
            ] : null;
        }

        if ($includeContrato) {
            $payload['contrato_atual'] = $terreno->contratoAtual ? [
                'contract_type' => $terreno->contratoAtual->contract_type,
                'contract_number' => $terreno->contratoAtual->contract_number,
                'status' => $terreno->contratoAtual->status,
                'signed_at' => optional($terreno->contratoAtual->signed_at)?->toAtomString(),
            ] : null;
        }

        if ($includeProjetos) {
            $payload['projetos'] = $terreno->projetos
                ->map(static fn ($projeto): array => [
                    'id' => $projeto->id,
                    'nome' => $projeto->nome,
                    'status' => $projeto->status,
                    'created_at' => optional($projeto->created_at)?->toAtomString(),
                ])->all();
        }

        if ($includeViabilidades) {
            $payload['ultimas_viabilidades'] = $terreno->viabilidades()
                ->select([
                    'viabilidades.id',
                    'viabilidades.terreno_id',
                    'viabilidades.version',
                    'viabilidades.is_current',
                    'viabilidades.status',
                    'viabilidades.approval_status',
                    'viabilidades.updated_at',
                ])
                ->orderByDesc('version')
                ->limit(5)
                ->get()
                ->map(static fn ($viabilidade): array => [
                    'id' => $viabilidade->id,
                    'version' => $viabilidade->version,
                    'is_current' => $viabilidade->is_current,
                    'status' => $viabilidade->status,
                    'approval_status' => $viabilidade->approval_status,
                    'updated_at' => optional($viabilidade->updated_at)?->toAtomString(),
                ])->all();
        }

        return AiToolResponse::ok($payload);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()
                ->required()
                ->description('ID numérico do terreno a consultar.'),
            'mode' => $schema->string()
                ->description('summary (padrão, payload magro) ou full (campos e datas completos).')
                ->enum(['summary', 'full']),
            'include_viabilidades' => $schema->boolean()
                ->description('Se true, inclui as últimas versões de viabilidade (máx. 5).'),
            'include_negociacao' => $schema->boolean()
                ->description('Se true, inclui negociação atual (default true em mode=full).'),
            'include_contrato' => $schema->boolean()
                ->description('Se true, inclui contrato atual (default true em mode=full).'),
            'include_projetos' => $schema->boolean()
                ->description('Se true, lista projetos (default true em mode=full).'),
        ];
    }
}
