<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
        return 'Estima VGV por benchmark de viabilidades similares (heurística histórica). NÃO é valor contábil oficial. Para VGV da viabilidade vigente do terreno, use GetViabilidadesTool.';
    }

    public function handle(Request $request): Stringable|string
    {
        $auth = app(AiToolAuth::class);
        if ($deny = $auth->ensureViewAny(
            Terreno::class,
            'Acesso negado: você não tem permissão para acessar benchmarks.'
        )) {
            return $deny;
        }

        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        if ($terrenoId <= 0) {
            return AiToolResponse::validation('Informe um terreno_id válido.');
        }

        $terreno = Terreno::find($terrenoId);
        if (! $terreno) {
            return AiToolResponse::empty("Terreno {$terrenoId} não encontrado.");
        }

        if ($deny = $auth->ensureView($terreno, "Acesso negado ao terreno {$terrenoId}.")) {
            return $deny;
        }

        $benchmark = $this->predictiveService->getVgvBenchmark($terreno);
        $payload = is_array($benchmark) ? $benchmark : ['result' => $benchmark];

        return AiToolResponse::ok(AiPredictivePayload::withMeta(
            $payload,
            isset($payload['count']) ? (int) $payload['count'] : null,
            'vgv_benchmark_historical'
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()->required()->description('ID do terreno para estimar VGV.'),
        ];
    }
}
