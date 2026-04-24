<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Repositories\Contracts\TerrenoExportRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TerrenoExportService
{
    public function __construct(
        private readonly TerrenoExportRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, \App\Models\Tenant\Terreno>
     */
    public function getTerrenosForExport(array $filters, string $tenantId): Collection
    {
        $cacheKey = "tenant:{$tenantId}:terrenos:export:pdf:" . md5(json_encode($filters));

        return Cache::tags(["tenant:{$tenantId}:terrenos"])
            ->remember($cacheKey, now()->addMinutes(10), function () use ($filters) {
                return $this->repository->queryForExport($filters);
            });
    }

    public function getTerrenoForSingleExport(int $id): ?\App\Models\Tenant\Terreno
    {
        return $this->repository->findForSingleExport($id);
    }

    public function getTerrenoForChecklist(int $id): ?\App\Models\Tenant\Terreno
    {
        return $this->repository->findForChecklist($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildExportData(Collection $terrenos, array $filters): array
    {
        return [
            'terrenos' => $terrenos,
            'totalTerrenos' => $terrenos->count(),
            'dataGeracao' => now()->format('d/m/Y H:i'),
            'filtros' => [
                'nome' => $filters['nome'] ?? null,
                'dataInicio' => $filters['data_inicio'] ?? null,
                'dataFim' => $filters['data_fim'] ?? null,
                'workflow_statuses' => collect($filters['workflow_statuses'] ?? [])
                    ->map(fn (string $code) => LandWorkflowService::statuses()[$code]['label'] ?? $code)
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSingleExportData(\App\Models\Tenant\Terreno $terreno, ?string $userName): array
    {
        $vgvEstimado = $terreno->terrenoProdutos->reduce(function ($acc, $p) {
            return $acc + ($p->unidades * ($p->valor ?? 0));
        }, 0);

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

    public function applyBrowsershotDefaults($browsershot): void
    {
        $chromePath = $this->resolveChromePath();
        if ($chromePath) {
            $browsershot->setChromePath($chromePath);
        }

        $browsershot->noSandbox();
    }
}
