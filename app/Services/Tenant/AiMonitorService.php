<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AiMonitorService
{
    /**
     * Detecta terrenos que estão parados (sem atualização > 30 dias).
     *
     * @return Collection<int, array<string, mixed>>
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
            $updatedAt = $t->getAttribute('updated_at');
            $daysInactive = $updatedAt ? $updatedAt->diffInDays(now()) : null;

            return [
                'type' => 'stalled_terrain',
                'severity' => $daysInactive !== null && $daysInactive > 60 ? 'high' : 'medium',
                'severity_score' => $daysInactive !== null && $daysInactive > 60 ? 80 : 50,
                'terrain_id' => $t->getKey(),
                'terrain_name' => (string) $t->getAttribute('nome'),
                'message' => 'Terreno parado há '.$daysInactive.' dias no estágio '.(string) $t->getAttribute('workflow_stage').'.',
                'suggestion' => 'Verificar status com responsável ou criar alerta de acompanhamento.',
                'details' => [
                    'stage' => (string) $t->getAttribute('workflow_stage'),
                    'status_code' => (string) $t->getAttribute('workflow_status_code'),
                    'days_inactive' => $daysInactive,
                    'last_update' => $updatedAt?->toDateString(),
                ],
            ];
        });
    }

    /**
     * Detecta inconsistências entre o stage do workflow e o estado real das entidades relacionadas.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function detectInconsistencies(int $limit): Collection
    {
        /** @var Collection<int, Terreno> $terrains */
        $terrains = Terreno::query()
            ->with(['viabilidadeAtual', 'comiteAtual'])
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])
            ->limit($limit)
            ->get();

        $alerts = collect();

        $terrains->each(function (Terreno $t) use ($alerts): void {
            $workflowStage = (string) $t->getAttribute('workflow_stage');
            $viabilidadeAtual = $t->viabilidadeAtual()->first();
            $comiteAtual = $t->comiteAtual()->first();
            $approvalStatus = $viabilidadeAtual?->getAttribute('approval_status');
            $committeeStatus = $comiteAtual?->getAttribute('status');
            $committeeUpdatedAt = $comiteAtual?->getAttribute('updated_at');

            if ($workflowStage === 'viabilidade' &&
                $approvalStatus !== 'aprovada') {
                $alerts->push([
                    'type' => 'workflow_inconsistency',
                    'severity' => 'high',
                    'severity_score' => 85,
                    'terrain_id' => (int) $t->getKey(),
                    'terrain_name' => (string) $t->getAttribute('nome'),
                    'message' => 'Está em viabilidade sem viabilidade aprovada.',
                    'suggestion' => 'Solicitar nova viabilidade ou retornar para em_analise.',
                    'details' => [
                        'stage' => $workflowStage,
                        'viability_status' => $approvalStatus,
                    ],
                ]);

                return;
            }

            if ($workflowStage === 'comite' &&
                $committeeStatus === 'em_andamento' &&
                $committeeUpdatedAt instanceof CarbonInterface &&
                $committeeUpdatedAt->diffInDays(now()) > 15) {
                $alerts->push([
                    'type' => 'workflow_inconsistency',
                    'severity' => 'medium',
                    'severity_score' => 60,
                    'terrain_id' => (int) $t->getKey(),
                    'terrain_name' => (string) $t->getAttribute('nome'),
                    'message' => 'Comitê em andamento há mais de 15 dias sem decisão.',
                    'suggestion' => 'Solicitar parecer urgente ao comitê.',
                    'details' => [
                        'stage' => $workflowStage,
                        'committee_status' => $committeeStatus,
                        'days_pending' => $committeeUpdatedAt->diffInDays(now()),
                    ],
                ]);
            }
        });

        return $alerts->values();
    }

    /**
     * Detecta tarefas e etapas de legalização atrasadas.
     *
     * @return Collection<int, array<string, mixed>>
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
            /** @var \Illuminate\Database\Eloquent\Collection<int, Task> $tasks */
            $tasks = $t->tasks()->get();
            foreach ($tasks as $task) {
                $dueDate = $task->getAttribute('due_date');
                $taskStatus = (string) $task->getAttribute('status');
                $taskPriority = (string) $task->getAttribute('priority');
                $taskTitle = (string) $task->getAttribute('title');

                if ($dueDate && $dueDate < now() && ! in_array($taskStatus, ['concluded', 'cancelled'], true)) {
                    $alerts->push([
                        'type' => 'overdue_task',
                        'severity' => $taskPriority === 'urgent' ? 'high' : 'medium',
                        'severity_score' => $taskPriority === 'urgent' ? 90 : 65,
                        'terrain_id' => (int) $t->getKey(),
                        'terrain_name' => (string) $t->getAttribute('nome'),
                        'message' => "Tarefa '{$taskTitle}' atrasada desde {$dueDate->toDateString()}.",
                        'suggestion' => 'Revisar e concluir tarefa ou reatribuir.',
                        'details' => [
                            'task_id' => (int) $task->getKey(),
                            'task_title' => $taskTitle,
                            'due_date' => $dueDate->toDateString(),
                            'priority' => $taskPriority,
                        ],
                    ]);
                }
            }

            /** @var Legalizacao|null $legalizacao */
            $legalizacao = $t->legalizacao()->first();
            if ($legalizacao) {
                $overdueEtapa = $legalizacao->etapas()
                    ->where('status', '!=', 'concluida')
                    ->where('due_date', '<', now())
                    ->first();

                if ($overdueEtapa) {
                    $alerts->push([
                        'type' => 'overdue_legalizacao',
                        'severity' => 'high',
                        'severity_score' => 80,
                        'terrain_id' => (int) $t->getKey(),
                        'terrain_name' => (string) $t->getAttribute('nome'),
                        'message' => "Etapa de legalização '".(string) $overdueEtapa->getAttribute('nome')."' atrasada.",
                        'suggestion' => 'Verificar pendências e atualizar status da etapa.',
                        'details' => [
                            'etapa_id' => $overdueEtapa->getKey(),
                            'etapa_nome' => $overdueEtapa->getAttribute('nome'),
                            'due_date' => $overdueEtapa->getAttribute('due_date'),
                        ],
                    ]);
                }
            }
        });

        return $alerts;
    }
}
