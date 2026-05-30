<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Terreno;
use App\Repositories\Contracts\TerrenoExportRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class TerrenoExportService
{
    public function __construct(
        private readonly TerrenoExportRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Terreno>
     */
    public function getTerrenosForExport(array $filters, string $tenantId): Collection
    {
        $encodedFilters = json_encode($filters);
        $cacheKeyPayload = is_string($encodedFilters) ? $encodedFilters : serialize($filters);
        $cacheKey = "tenant:{$tenantId}:terrenos:export:pdf:" . md5($cacheKeyPayload);

        return Cache::tags(["tenant:{$tenantId}:terrenos"])
            ->remember($cacheKey, now()->addMinutes(10), function () use ($filters) {
                return $this->repository->queryForExport($filters);
            });
    }

    public function getTerrenoForSingleExport(int $id): ?Terreno
    {
        return $this->repository->findForSingleExport($id);
    }

    public function getTerrenoForChecklist(int $id): ?Terreno
    {
        return $this->repository->findForChecklist($id);
    }

    /**
     * @param  Collection<int, Terreno>  $terrenos
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildExportData(Collection $terrenos, array $filters): array
    {
        $workflowStatuses = is_array($filters['workflow_statuses'] ?? null) ? $filters['workflow_statuses'] : [];

        return [
            'terrenos' => $terrenos,
            'totalTerrenos' => $terrenos->count(),
            'dataGeracao' => now()->format('d/m/Y H:i'),
            'filtros' => [
                'nome' => $filters['nome'] ?? null,
                'dataInicio' => $filters['data_inicio'] ?? null,
                'dataFim' => $filters['data_fim'] ?? null,
                'workflow_statuses' => collect($workflowStatuses)
                    ->map(fn (string $code) => LandWorkflowService::statuses()[$code]['label'] ?? $code)
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSingleExportData(Terreno $terreno, ?string $userName): array
    {
        $vgvEstimado = $terreno->terrenoProdutos()->get()->reduce(function (float $acc, mixed $p): float {
            return $acc + (((float) $p->getAttribute('unidades')) * ((float) $p->getAttribute('valor')));
        }, 0.0);

        return [
            'terreno' => $terreno,
            'vgvEstimado' => $vgvEstimado,
            'dataGeracao' => now()->format('d/m/Y H:i'),
            'geradoPor' => $userName ?? 'Sistema',
        ];
    }

    public function resolveChromePath(): ?string
    {
        $candidates = array_filter([
            env('BROWSERSHOT_CHROME_PATH'),
            env('PUPPETEER_EXECUTABLE_PATH'),
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function applyBrowsershotDefaults(mixed $browsershot): void
    {
        if (! is_object($browsershot) || ! method_exists($browsershot, 'noSandbox')) {
            return;
        }

        $chromePath = $this->resolveChromePath();
        if ($chromePath && method_exists($browsershot, 'setChromePath')) {
            call_user_func([$browsershot, 'setChromePath'], $chromePath);
        }

        call_user_func([$browsershot, 'noSandbox']);
    }
}
