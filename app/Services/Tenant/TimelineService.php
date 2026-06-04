<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Comment;
use App\Models\Tenant\EntityActivity;
use App\Models\Tenant\StatusHistory;
use App\Models\Tenant\Task;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TimelineService
{
    private const int MAX_PER_SOURCE = 200;

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function getForTerreno(int $terrenoId, int $page = 1, int $perPage = 50): LengthAwarePaginator
    {
        $activities = $this->loadActivities($terrenoId);
        $statusHistories = $this->loadStatusHistories($terrenoId);
        $tasks = $this->loadTasks($terrenoId);
        $comments = $this->loadComments($terrenoId);

        $merged = $activities
            ->concat($statusHistories)
            ->concat($tasks)
            ->concat($comments)
            ->sortByDesc('timestamp')
            ->values();

        $total = $merged->count();
        $items = $merged->forPage($page, $perPage);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadActivities(int $terrenoId): Collection
    {
        $models = EntityActivity::query()
            ->with('user')
            ->where('terreno_id', $terrenoId)
            ->latest('happened_at')
            ->take(self::MAX_PER_SOURCE)
            ->get();

        return $models->map(function (EntityActivity $a): array {
            return [
                'id' => $a->id,
                'type' => 'activity',
                'timestamp' => $a->happened_at?->toIso8601String(),
                'user_id' => $a->user_id,
                'user_name' => $a->relationLoaded('user') ? $a->user?->name : null,
                'summary' => $a->summary,
                'data' => [
                    'action' => $a->action,
                    'entity_type' => $a->entity_type,
                    'entity_id' => $a->entity_id,
                    'payload' => $a->payload_json,
                ],
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadStatusHistories(int $terrenoId): Collection
    {
        $models = StatusHistory::query()
            ->with('changedBy')
            ->where('terreno_id', $terrenoId)
            ->latest('created_at')
            ->take(self::MAX_PER_SOURCE)
            ->get();

        return $models->map(function (StatusHistory $s): array {
            return [
                'id' => $s->id,
                'type' => 'status_history',
                'timestamp' => $s->created_at?->toIso8601String(),
                'user_id' => $s->changed_by,
                'user_name' => $s->relationLoaded('changedBy') ? $s->changedBy?->name : null,
                'summary' => $this->statusSummary($s),
                'data' => [
                    'old_stage' => $s->old_stage,
                    'old_status_code' => $s->old_status_code,
                    'new_stage' => $s->new_stage,
                    'new_status_code' => $s->new_status_code,
                    'reason_code' => $s->reason_code,
                    'reason' => $s->reason,
                    'metadata' => $s->metadata_json,
                ],
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadTasks(int $terrenoId): Collection
    {
        $models = Task::query()
            ->with('assignedUser')
            ->where('terreno_id', $terrenoId)
            ->latest('created_at')
            ->take(self::MAX_PER_SOURCE)
            ->get();

        return $models->map(function (Task $t): array {
            return [
                'id' => $t->id,
                'type' => 'task',
                'timestamp' => $t->created_at?->toIso8601String(),
                'user_id' => $t->assigned_to,
                'user_name' => $t->relationLoaded('assignedUser') ? $t->assignedUser?->name : null,
                'summary' => $t->title,
                'data' => [
                    'title' => $t->title,
                    'description' => $t->description,
                    'status' => $t->status,
                    'priority' => $t->priority,
                    'due_date' => $t->due_date?->format('Y-m-d'),
                    'completed_at' => $t->completed_at?->toIso8601String(),
                ],
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadComments(int $terrenoId): Collection
    {
        $models = Comment::query()
            ->with('user')
            ->where('terreno_id', $terrenoId)
            ->latest('created_at')
            ->take(self::MAX_PER_SOURCE)
            ->get();

        return $models->map(function (Comment $c): array {
            return [
                'id' => $c->id,
                'type' => 'comment',
                'timestamp' => $c->created_at?->toIso8601String(),
                'user_id' => $c->user_id,
                'user_name' => $c->relationLoaded('user') ? $c->user?->name : null,
                'summary' => Str::limit($c->comment, 120),
                'data' => [
                    'comment' => $c->comment,
                ],
            ];
        });
    }

    private function statusSummary(StatusHistory $s): string
    {
        $from = $s->old_status_code ?? $s->old_stage ?? '—';
        $to = $s->new_status_code ?? $s->new_stage ?? '—';

        $summary = "{$from} → {$to}";

        if ($s->reason) {
            $summary .= ": {$s->reason}";
        }

        return $summary;
    }
}
