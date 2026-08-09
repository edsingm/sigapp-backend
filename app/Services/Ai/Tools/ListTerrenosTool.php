<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListTerrenosTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Lista terrenos com filtros (busca, etapa, status, cidade por nome ou código, parados, ordenação). Use para varredura inicial da carteira — não para totais agregados (dashboard) nem alertas proativos.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($deny = app(AiToolAuth::class)->ensureViewAny(
            Terreno::class,
            'Acesso negado: você não tem permissão para listar terrenos.'
        )) {
            return $deny;
        }

        $search = trim((string) ($request['search'] ?? ''));
        $workflowStage = trim((string) ($request['workflow_stage'] ?? ''));
        $workflowStatus = trim((string) ($request['workflow_status_code'] ?? ''));
        $cidadeCode = trim((string) ($request['cidade_code'] ?? ''));
        $cidade = trim((string) ($request['cidade'] ?? ''));
        $somenteParados = filter_var($request['somente_parados'] ?? false, FILTER_VALIDATE_BOOL);
        $paradosDias = max(1, min(365, (int) ($request['parados_dias'] ?? 30)));
        $orderBy = strtolower(trim((string) ($request['order_by'] ?? 'updated_at')));
        if (! in_array($orderBy, ['updated_at', 'valor', 'nome'], true)) {
            $orderBy = 'updated_at';
        }
        $limit = AiToolResponse::clampLimit($request['limit'] ?? 10);

        $query = Terreno::query()
            ->with([
                'viabilidadeAtual' => static function ($q): void {
                    $q->select([
                        'viabilidades.id',
                        'viabilidades.terreno_id',
                        'viabilidades.version',
                        'viabilidades.status',
                        'viabilidades.approval_status',
                        'viabilidades.updated_at',
                    ]);
                },
                'cidade:code,city',
            ])
            ->select([
                'id',
                'nome',
                'endereco',
                'cidade_code',
                'estado',
                'area_calculada',
                'valor',
                'workflow_stage',
                'workflow_status_code',
                'updated_at',
            ]);

        if ($orderBy === 'valor') {
            $query->orderByDesc('valor');
        } elseif ($orderBy === 'nome') {
            $query->orderBy('nome');
        } else {
            $query->orderByDesc('updated_at');
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('nome', 'like', "%{$search}%")
                    ->orWhere('endereco', 'like', "%{$search}%");
            });
        }

        if ($workflowStage !== '') {
            $query->where('workflow_stage', $workflowStage);
        }

        if ($workflowStatus !== '') {
            $query->where('workflow_status_code', $workflowStatus);
        }

        if ($cidadeCode !== '') {
            $query->where('cidade_code', $cidadeCode);
        }

        if ($cidade !== '') {
            $needle = '%'.mb_strtolower($cidade).'%';
            $query->whereHas('cidade', static function ($q) use ($needle): void {
                $q->whereRaw('LOWER(city) LIKE ?', [$needle]);
            });
        }

        if ($somenteParados) {
            $query->where('updated_at', '<', now()->subDays($paradosDias))
                ->whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado']);
        }

        $total = (int) $query->count();
        /** @var Collection<int, Terreno> $terrenos */
        $terrenos = $query->limit($limit)->get();
        $terrenos = app(AiToolAuth::class)->filterByView(
            $terrenos,
            static fn (Terreno $terreno): Terreno => $terreno,
        );

        if ($terrenos->isEmpty()) {
            return AiToolResponse::empty(
                'Nenhum terreno encontrado para os filtros informados.',
                [
                    'items' => [],
                    'meta' => AiToolResponse::listMeta($total, 0, $limit),
                ]
            );
        }

        $items = array_map(static function (Terreno $terreno): array {
            return [
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
                'viabilidade_atual' => $terreno->viabilidadeAtual ? [
                    'id' => $terreno->viabilidadeAtual->id,
                    'version' => $terreno->viabilidadeAtual->version,
                    'status' => $terreno->viabilidadeAtual->status,
                    'approval_status' => $terreno->viabilidadeAtual->approval_status,
                    'updated_at' => optional($terreno->viabilidadeAtual->updated_at)?->toAtomString(),
                ] : null,
            ];
        }, $terrenos->all());

        return AiToolResponse::ok([
            'items' => $items,
            'meta' => AiToolResponse::listMeta($total, count($items), $limit),
            'order_by' => $orderBy,
            'somente_parados' => $somenteParados,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Busca textual em nome ou endereço do terreno.'),
            'workflow_stage' => $schema->string()
                ->description('Etapa do workflow.')
                ->enum([
                    'captacao',
                    'viabilidade',
                    'comite',
                    'negociacao_contrato',
                    'legalizacao',
                    'encerramento',
                ]),
            'workflow_status_code' => $schema->string()
                ->description('Status detalhado do workflow.')
                ->enum([
                    'em_analise',
                    'aguardando_viabilidade',
                    'viabilidade_aprovada',
                    'aguardando_comite',
                    'negociacao_minuta',
                    'contrato_assinado',
                    'legalizando',
                    'legalizado_finalizado',
                    'descartado',
                    'arquivado',
                ]),
            'cidade_code' => $schema->string()
                ->description('Código IBGE da cidade (quando conhecido).'),
            'cidade' => $schema->string()
                ->description('Nome da cidade (busca parcial, preferível ao código quando o LLM só tem o nome).'),
            'somente_parados' => $schema->boolean()
                ->description('Se true, só terrenos sem atualização recente (padrão 30 dias).'),
            'parados_dias' => $schema->integer()
                ->description('Janela em dias para somente_parados (padrão 30).')
                ->min(1),
            'order_by' => $schema->string()
                ->description('Ordenação: updated_at (padrão), valor ou nome.')
                ->enum(['updated_at', 'valor', 'nome']),
            'limit' => $schema->integer()
                ->description('Máximo de itens retornados (padrão 10, máximo 50).')
                ->min(1),
        ];
    }
}
