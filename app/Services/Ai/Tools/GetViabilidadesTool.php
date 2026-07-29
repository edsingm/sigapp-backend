<?php

namespace App\Services\Ai\Tools;

use App\Enums\ViabilidadeApprovalStatus;
use App\Models\Tenant\Viabilidade;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetViabilidadesTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Consulta viabilidades por terreno/status/aprovação. Para "viabilidade aprovada" use approval_status=aprovada (não confunda com workflow_status do terreno). Com terreno_id, default somente_atual=true. include_dre=summary|full. Sem fluxo mensal.';
    }

    public function handle(Request $request): Stringable|string
    {
        $auth = app(AiToolAuth::class);
        if ($deny = $auth->ensureFeature(
            'viabilities.enabled',
            'Acesso negado: seu plano não inclui viabilidades.'
        )) {
            return $deny;
        }

        if ($deny = $auth->ensureViewAny(
            Viabilidade::class,
            'Acesso negado: você não tem permissão para acessar viabilidades.'
        )) {
            return $deny;
        }

        $terrenoId = (int) ($request['terreno_id'] ?? 0);

        if ($terrenoId > 0) {
            $terrenoOrDeny = $auth->ensureTerrenoView($terrenoId);
            if (is_string($terrenoOrDeny)) {
                return $terrenoOrDeny;
            }
        }

        $status = trim((string) ($request['status'] ?? ''));
        $approvalStatus = trim((string) ($request['approval_status'] ?? ''));
        $somenteDecididas = filter_var($request['somente_decididas'] ?? false, FILTER_VALIDATE_BOOL);
        $includeDre = strtolower(trim((string) ($request['include_dre'] ?? 'summary')));
        if (! in_array($includeDre, ['summary', 'full'], true)) {
            $includeDre = 'summary';
        }

        // Default: somente versão atual quando filtrando por terreno e o caller não especificou.
        if (! array_key_exists('somente_atual', $request->toArray())) {
            $somenteAtual = $terrenoId > 0;
        } else {
            $somenteAtual = filter_var($request['somente_atual'], FILTER_VALIDATE_BOOL);
        }

        $limit = AiToolResponse::clampLimit($request['limit'] ?? 20);

        $query = Viabilidade::query()
            ->with([
                'terreno' => static function ($q): void {
                    $q->select(['id', 'nome', 'endereco', 'cidade_code', 'estado'])
                        ->with('cidade:code,city');
                },
            ])
            ->select([
                'id',
                'terreno_id',
                'version',
                'is_current',
                'status',
                'approval_status',
                'approval_requested_at',
                'approval_decided_at',
                'resultados_dre',
                'premissas_snapshot',
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('updated_at');

        if ($terrenoId > 0) {
            $query->where('terreno_id', $terrenoId);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($approvalStatus !== '') {
            $query->where('approval_status', $approvalStatus);
        }

        if ($somenteDecididas) {
            $query->whereIn('approval_status', [
                ViabilidadeApprovalStatus::Aprovada->value,
                ViabilidadeApprovalStatus::Rejeitada->value,
            ]);
        }

        if ($somenteAtual) {
            $query->where('is_current', true);
        }

        $total = (int) $query->count();
        $viabilidades = $query->limit($limit)->get();

        if ($viabilidades->isEmpty()) {
            return AiToolResponse::empty(
                'Nenhuma viabilidade encontrada para os filtros informados.',
                [
                    'items' => [],
                    'meta' => AiToolResponse::listMeta($total, 0, $limit),
                    'somente_atual' => $somenteAtual,
                    'include_dre' => $includeDre,
                ]
            );
        }

        $items = $viabilidades->map(function (Viabilidade $viabilidade) use ($includeDre): array {
            $dre = is_array($viabilidade->resultados_dre) ? $viabilidade->resultados_dre : [];
            $indicadores = is_array($dre['indicadores'] ?? null) ? $dre['indicadores'] : [];
            $snapshot = is_array($viabilidade->premissas_snapshot) ? $viabilidade->premissas_snapshot : [];

            $resumo = [
                'vgv' => $dre['vgv'] ?? null,
                'total_unidades' => $dre['totalUnidades'] ?? null,
                'margem_liquida_percentual' => $indicadores['margem_liquida_percentual'] ?? null,
                'tir_operacional' => $indicadores['tir_operacional'] ?? null,
                'tir_financeira' => $indicadores['tir_financeira'] ?? null,
                'lucro_liquido' => is_array($dre['dre_itens'] ?? null)
                    ? ($dre['dre_itens']['lucro_liquido_projeto'] ?? null)
                    : null,
            ];

            if ($includeDre === 'full') {
                $resumo['exposicao_maxima_operacional'] = $indicadores['exposicao_maxima_operacional'] ?? null;
                $resumo['exposicao_maxima_financeira'] = $indicadores['exposicao_maxima_financeira'] ?? null;
                $resumo['payback_operacional_meses'] = $indicadores['payback_operacional_meses']
                    ?? $indicadores['payback_operacional']
                    ?? null;
                $resumo['totais'] = is_array($dre['totais'] ?? null) ? $dre['totais'] : null;
                // Nunca devolver fluxo_mensal completo no chat.
            }

            return [
                'id' => $viabilidade->id,
                'terreno_id' => $viabilidade->terreno_id,
                'terreno' => $viabilidade->terreno ? [
                    'nome' => $viabilidade->terreno->nome,
                    'endereco' => $viabilidade->terreno->endereco,
                    'cidade' => $viabilidade->terreno->cidade?->city ?? $viabilidade->terreno->cidade_code,
                    'estado' => $viabilidade->terreno->estado,
                ] : null,
                'version' => $viabilidade->version,
                'is_current' => $viabilidade->is_current,
                'status' => $viabilidade->status,
                'approval_status' => $viabilidade->approval_status,
                'approval_requested_at' => optional($viabilidade->approval_requested_at)?->toAtomString(),
                'approval_decided_at' => optional($viabilidade->approval_decided_at)?->toAtomString(),
                'resumo_financeiro' => $resumo,
                'engine_version' => $snapshot['calculation_engine_version'] ?? null,
                'updated_at' => optional($viabilidade->updated_at)?->toAtomString(),
            ];
        })->all();

        return AiToolResponse::ok([
            'somente_atual' => $somenteAtual,
            'include_dre' => $includeDre,
            'sample_note' => $somenteDecididas
                ? 'Amostra restrita a decisões finais (aprovada/rejeitada).'
                : 'Amostra pode incluir estudos pendentes/em aprovação; use somente_decididas para métricas de aprovação.',
            'items' => $items,
            'meta' => AiToolResponse::listMeta($total, count($items), $limit),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()
                ->description('Filtra viabilidades de um terreno específico.'),
            'status' => $schema->string()
                ->description('Status do estudo de viabilidade.'),
            'approval_status' => $schema->string()
                ->description('Status de aprovação (ex.: pendente, aprovada, rejeitada).'),
            'somente_atual' => $schema->boolean()
                ->description('Se true, só is_current. Default true quando terreno_id informado.'),
            'somente_decididas' => $schema->boolean()
                ->description('Se true, restringe a aprovações finais (aprovada/rejeitada).'),
            'include_dre' => $schema->string()
                ->description('summary (padrão) ou full (mais indicadores, sem fluxo mensal).')
                ->enum(['summary', 'full']),
            'limit' => $schema->integer()
                ->description('Máximo de itens (padrão 20, máximo 50).')
                ->min(1),
        ];
    }
}
