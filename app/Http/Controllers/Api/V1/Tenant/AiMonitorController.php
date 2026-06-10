<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Terreno;
use App\Services\Tenant\AiMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class AiMonitorController extends Controller
{
    public function __construct(
        protected AiMonitorService $aiMonitorService
    ) {}

    /**
     * Retorna alertas proativos do portfólio.
     */
    public function index(Request $request): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $focusArea = $request->get('focus_area');
        $limit = min($request->integer('limit', 50), 200);

        /** @var Collection<int, array<string, mixed>> $alerts */
        $alerts = collect();

        if ($focusArea === null || $focusArea === 'stalled') {
            $alerts = $alerts->merge($this->detectStalledTerrains($limit));
        }

        if ($focusArea === null || $focusArea === 'inconsistencies') {
            $alerts = $alerts->merge($this->detectInconsistencies($limit));
        }

        if ($focusArea === null || $focusArea === 'overdue') {
            $alerts = $alerts->merge($this->detectOverdueItems($limit));
        }

        $alerts = $alerts->sortByDesc('severity_score')->values()->take($limit);

        return new JsonResponse([
            'data' => [
                'total_alerts' => $alerts->count(),
                'alerts' => $alerts,
                'scan_timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function detectStalledTerrains(int $limit): array
    {
        /** @var list<array<string, mixed>> $alerts */
        $alerts = $this->aiMonitorService->detectStalledTerrains($limit)->values()->all();

        return $alerts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function detectInconsistencies(int $limit): array
    {
        /** @var list<array<string, mixed>> $alerts */
        $alerts = $this->aiMonitorService->detectInconsistencies($limit)->values()->all();

        return $alerts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function detectOverdueItems(int $limit): array
    {
        /** @var list<array<string, mixed>> $alerts */
        $alerts = $this->aiMonitorService->detectOverdueItems($limit)->values()->all();

        return $alerts;
    }
}
