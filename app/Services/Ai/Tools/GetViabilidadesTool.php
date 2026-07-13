<?php

namespace App\Services\Ai\Tools;

use App\Enums\ViabilidadeApprovalStatus;
use App\Models\Tenant\Viabilidade;
use App\Services\PlanMatrixService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetViabilidadesTool implements Tool
{
    public function __construct(private readonly PlanMatrixService $planMatrix) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Consulta viabilidades por terreno, status e aprovação, retornando resumo financeiro (sem fluxo mensal completo).';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        $status = trim((string) ($request['status'] ?? ''));
        $approvalStatus = trim((string) ($request['approval_status'] ?? ''));
        $somenteAtual = filter_var($request['somente_atual'] ?? false, FILTER_VALIDATE_BOOL);
        $somenteDecididas = filter_var($request['somente_decididas'] ?? false, FILTER_VALIDATE_BOOL);
        $limit = max(1, min((int) ($request['limit'] ?? 20), 50));

        $tenant = tenancy()->tenant;
        if (! $tenant || ! $this->planMatrix->hasFeatureForTenant($tenant, 'viabilities.enabled')) {
            return 'Acesso negado: seu plano não inclui viabilidades.';
        }

        if (Gate::denies('viewAny', Viabilidade::class)) {
            return 'Acesso negado: você não tem permissão para acessar viabilidades.';
        }

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

        $viabilidades = $query->limit($limit)->get();

        if ($viabilidades->isEmpty()) {
            return 'Nenhuma viabilidade encontrada para os filtros informados.';
        }

        $payload = [
            'total' => $viabilidades->count(),
            'sample_note' => $somenteDecididas
                ? 'Amostra restrita a decisões finais (aprovada/rejeitada).'
                : 'Amostra pode incluir estudos pendentes/em aprovação; use somente_decididas para métricas de aprovação.',
            'items' => $viabilidades->map(static function (Viabilidade $viabilidade): array {
                $dre = is_array($viabilidade->resultados_dre) ? $viabilidade->resultados_dre : [];
                $indicadores = is_array($dre['indicadores'] ?? null) ? $dre['indicadores'] : [];
                $snapshot = is_array($viabilidade->premissas_snapshot) ? $viabilidade->premissas_snapshot : [];

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
                    'resumo_financeiro' => [
                        'vgv' => $dre['vgv'] ?? null,
                        'total_unidades' => $dre['totalUnidades'] ?? null,
                        'margem_liquida_percentual' => $indicadores['margem_liquida_percentual'] ?? null,
                        'tir_operacional' => $indicadores['tir_operacional'] ?? null,
                        'tir_financeira' => $indicadores['tir_financeira'] ?? null,
                        'exposicao_maxima_operacional' => $indicadores['exposicao_maxima_operacional'] ?? null,
                        'exposicao_maxima_financeira' => $indicadores['exposicao_maxima_financeira'] ?? null,
                        'payback_operacional_meses' => $indicadores['payback_operacional_meses']
                            ?? $indicadores['payback_operacional']
                            ?? null,
                        'lucro_liquido' => is_array($dre['dre_itens'] ?? null)
                            ? ($dre['dre_itens']['lucro_liquido_projeto'] ?? null)
                            : null,
                    ],
                    'engine_version' => $snapshot['calculation_engine_version'] ?? null,
                    'result_hash' => $snapshot['result_hash'] ?? null,
                    'created_at' => optional($viabilidade->created_at)?->toAtomString(),
                    'updated_at' => optional($viabilidade->updated_at)?->toAtomString(),
                ];
            })->all(),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar viabilidades.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer(),
            'status' => $schema->string(),
            'approval_status' => $schema->string(),
            'somente_atual' => $schema->boolean(),
            'somente_decididas' => $schema->boolean(),
            'limit' => $schema->integer(),
        ];
    }
}
