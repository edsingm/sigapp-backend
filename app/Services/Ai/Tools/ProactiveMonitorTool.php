<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\Tenant\LandWorkflowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ProactiveMonitorTool implements Tool
{
    public function __construct(
        protected LandWorkflowService $workflowService
    ) {}

    public function description(): Stringable|string
    {
        return 'Analisa o portfólio e retorna alertas proativos: terrenos parados, inconsistências de workflow, viabilidades reprovas, legalizações atrasadas e tarefas atrasadas.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para monitorar o portfólio.';
        }

        $focusArea = trim((string) ($request['focus_area'] ?? ''));
        $limit = max(1, min((int) ($request['limit'] ?? 50), 200));

        $alerts = [];

        if ($focusArea === '' || $focusArea === 'stalled') {
            $alerts = [...$alerts, ...$this->detectStalledTerrains($limit)];
        }

        if ($focusArea === '' || $focusArea === 'inconsistencies') {
            $alerts = [...$alerts, ...$this->detectInconsistencies($limit)];
        }

        if ($focusArea === '' || $focusArea === 'overdue') {
            $alerts = [...$alerts, ...$this->detectOverdueItems($limit)];
        }

        if (empty($alerts)) {
            return 'Nenhum alerta encontrado para os filtros informados.';
        }

        usort($alerts, static fn ($a, $b) => $b['severity_score'] <=> $a['severity_score']);

        $payload = [
            'total_alerts' => count($alerts),
            'alerts' => array_slice($alerts, 0, $limit),
            'scan_timestamp' => now()->toIso8601String(),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar alertas.';
    }

    /**
     * @return array<int, array{type: string, severity: string, severity_score: int, terrain_id: int, terrain_name: string, message: string, suggestion: string, details: array}>
     */
    protected function detectStalledTerrains(int $limit): array
    {
        $threshold = now()->subDays(30);
        $alerts = [];

        Terreno::query()
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])
            ->where(function ($q) use ($threshold) {
                $q->whereNull('workflow_status_changed_at')
                    ->orWhere('updated_at', '<', $threshold);
            })
            ->limit($limit)
            ->get(['id', 'nome', 'workflow_stage', 'workflow_status_code', 'updated_at'])
            ->each(function (Terreno $t) use (&$alerts) {
                $daysInactive = $t->updated_at ? $t->updated_at->diffInDays(now()) : null;
                $alerts[] = [
                    'type' => 'stalled_terrain',
                    'severity' => $daysInactive !== null && $daysInactive > 60 ? 'high' : 'medium',
                    'severity_score' => $daysInactive !== null && $daysInactive > 60 ? 80 : 50,
                    'terrain_id' => $t->id,
                    'terrain_name' => $t->nome,
                    'message' => "Terreno parado há {$daysInactive} dias no estágio {$t->workflow_stage}.",
                    'suggestion' => 'Verificar status com responsável ou criar alerta de acompanhamento.',
                    'details' => [
                        'stage' => $t->workflow_stage,
                        'status_code' => $t->workflow_status_code,
                        'days_inactive' => $daysInactive,
                        'last_update' => optional($t->updated_at)?->toDateString(),
                    ],
                ];
            });

        return $alerts;
    }

    /**
     * @return array<int, array{type: string, severity: string, severity_score: int, terrain_id: int, terrain_name: string, message: string, suggestion: string, details: array}>
     */
    protected function detectInconsistencies(int $limit): array
    {
        $alerts = [];

        Terreno::query()
            ->with(['viabilidadeAtual', 'comiteAtual'])
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])
            ->limit($limit)
            ->get()
            ->each(function (Terreno $t) use (&$alerts) {
                if ($t->workflow_stage === 'viabilidade' &&
                    $t->viabilidadeAtual?->approval_status !== 'aprovada') {
                    $alerts[] = [
                        'type' => 'workflow_inconsistency',
                        'severity' => 'high',
                        'severity_score' => 85,
                        'terrain_id' => $t->id,
                        'terrain_name' => $t->nome,
                        'message' => 'Está em viabilidade sem viabilidade aprovada.',
                        'suggestion' => 'Solicitar nova viabilidade ou retornar para em_analise.',
                        'details' => [
                            'stage' => $t->workflow_stage,
                            'viability_status' => $t->viabilidadeAtual?->approval_status,
                        ],
                    ];
                }

                if ($t->workflow_stage === 'comite' &&
                    $t->comiteAtual?->status === 'em_andamento' &&
                    $t->comiteAtual?->updated_at &&
                    $t->comiteAtual->updated_at->diffInDays(now()) > 15) {
                    $alerts[] = [
                        'type' => 'workflow_inconsistency',
                        'severity' => 'medium',
                        'severity_score' => 60,
                        'terrain_id' => $t->id,
                        'terrain_name' => $t->nome,
                        'message' => 'Comitê em andamento há mais de 15 dias sem decisão.',
                        'suggestion' => 'Solicitar parecer urgente ao comitê.',
                        'details' => [
                            'stage' => $t->workflow_stage,
                            'committee_status' => $t->comiteAtual?->status,
                            'days_pending' => $t->comiteAtual->updated_at->diffInDays(now()),
                        ],
                    ];
                }
            });

        return $alerts;
    }

    /**
     * @return array<int, array{type: string, severity: string, severity_score: int, terrain_id: int, terrain_name: string, message: string, suggestion: string, details: mixed}>
     */
    protected function detectOverdueItems(int $limit): array
    {
        $alerts = [];

        Terreno::query()
            ->with(['tasks', 'legalizacao.etapas', 'legalizacao.pendencias'])
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])
            ->limit($limit)
            ->get()
            ->each(function (Terreno $t) use (&$alerts) {
                $overdueTasks = $t->tasks->filter(
                    fn ($task) => $task->due_date && $task->due_date < now() && ! in_array($task->status, ['concluded', 'cancelled']),
                );

                foreach ($overdueTasks as $task) {
                    $alerts[] = [
                        'type' => 'overdue_task',
                        'severity' => $task->priority === 'urgent' ? 'high' : 'medium',
                        'severity_score' => $task->priority === 'urgent' ? 90 : 65,
                        'terrain_id' => $t->id,
                        'terrain_name' => $t->nome,
                        'message' => "Tarefa '{$task->title}' atrasada desde {$task->due_date->toDateString()}.",
                        'suggestion' => 'Revisar e concluir tarefa ou reatribuir.',
                        'details' => [
                            'task_id' => $task->id,
                            'task_title' => $task->title,
                            'due_date' => $task->due_date->toDateString(),
                            'priority' => $task->priority,
                        ],
                    ];
                }

                if ($t->legalizacao) {
                    $overdueEtapa = $t->legalizacao->etapas()
                        ->where('status', '!=', 'concluida')
                        ->where('due_date', '<', now())
                        ->first();

                    if ($overdueEtapa) {
                        $alerts[] = [
                            'type' => 'overdue_legalizacao',
                            'severity' => 'high',
                            'severity_score' => 80,
                            'terrain_id' => $t->id,
                            'terrain_name' => $t->nome,
                            'message' => "Etapa de legalização '{$overdueEtapa->nome}' atrasada.",
                            'suggestion' => 'Verificar pendências e atualizar status da etapa.',
                            'details' => [
                                'etapa_id' => $overdueEtapa->id,
                                'etapa_nome' => $overdueEtapa->nome,
                                'due_date' => $overdueEtapa->due_date,
                            ],
                        ];
                    }
                }
            });

        return $alerts;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'focus_area' => $schema->string(),
            'limit' => $schema->integer(),
        ];
    }
}
