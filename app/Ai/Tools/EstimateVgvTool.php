<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\AiPredictiveAnalysisService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class EstimateVgvTool implements Tool
{
    public function __construct(
        protected AiPredictiveAnalysisService $predictiveService
    ) {}

    public function description(): Stringable|string
    {
        return 'Estima o VGV (Valor Geral de Vendas) de um terreno com base em benchmark de viabilidades similares realizadas no passado.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar benchmarks.';
        }

        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        if ($terrenoId <= 0) {
            return 'Informe um terreno_id válido.';
        }

        $terreno = Terreno::find($terrenoId);
        if (! $terreno) {
            return "Terreno {$terrenoId} não encontrado.";
        }

        if (Gate::denies('view', $terreno)) {
            return "Acesso negado ao terreno {$terrenoId}.";
        }

        $benchmark = $this->predictiveService->getVgvBenchmark($terreno);

        return json_encode($benchmark, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar benchmark.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()->required(),
        ];
    }
}
