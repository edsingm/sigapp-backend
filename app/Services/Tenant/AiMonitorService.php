<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Support\Collection;

class AiMonitorService
{
    /**
     * Detecta terrenos que estão parados (sem atualização > 30 dias).
     *
     * @return Collection<int, array>
     */
    public function detectStalledTerrains(int $limit): Collection
    {
        $threshold = now()->subDays(30);

        /** @var Collection<int, Terreno> $terrains */
        $terrains = Terreno::query()
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])
            ->where(function ($q) use ($threshold) {
                $q->whereNull('workflow_status_changed_at')
                    ->orWhere('updated_at', '<', $threshold);
            })
            ->limit($limit)
            ->get();

        return $terrains->map(function (Terreno $t): array {
            $daysInactive = $t->updated_at ? $t->updated_at->diffInDays(now()) : null;

            return [
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
                    'last_update' => $t->updated_at?->toDateString(),
                ],
            ];
        });
    }

    /**
     * Detecta inconsistências entre o stage do workflow e o estado real das entidades relacionadas.
     *
     * @return Collection<int, array>
     */
    public function detectInconsistencies(int $limit): Collection
    {
        /** @var Collection<int, Terreno> $terrains */
        $terrains = Terreno::query()
            ->with(['viabilidadeAtual', 'comiteAtual'])
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])
            ->limit($limit)
            ->get();

        return $terrains->map(function (Terreno $t): array|null {
            if ($t->workflow_stage === 'viabilidade' &&
                $t->viabilidadeAtual?->approval_status !== 'aprovada') {
                return [
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
                return [
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

            return null;
        })->filter();
    }

    /**
     * Detecta tarefas e etapas de legalização atrasadas.
     *
     * @return Collection<int, array>
     */
    public function detectOverdueItems(int $limit): Collection
    {
        $alerts = collect();

        /** @var Collection<int, Terreno> $terrains */
        $terrains = Terreno::query()
            ->with(['tasks', 'legalizacao.etapas'])
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])
            ->limit($limit)
            ->get();

        $terrains->each(function (Terreno $t) use (&$alerts): void {
            foreach ($t->tasks as $task) {
                if ($task->due_date && $task->due_date < now() && ! in_array($task->status, ['concluded', 'cancelled'])) {
                    $alerts->push([
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
                    ]);
                }
            }

            if ($t->legalizacao) {
                $overdueEtapa = $t->legalizacao->etapas()
                    ->where('status', '!=', 'concluida')
                    ->where('due_date', '<', now())
                    ->first();

                if ($overdueEtapa) {
                    $alerts->push([
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
                    ]);
                }
            }
        });

        return $alerts;
    }
}
