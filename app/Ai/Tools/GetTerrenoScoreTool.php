<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\AiScoringService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetTerrenoScoreTool implements Tool
{
    public function __construct(
        protected AiScoringService $scoringService
    ) {}

    public function description(): Stringable|string
    {
        return 'Retorna o score de priorização de um terreno (0-100) com explicação dos fatores que influenciaram.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar scores.';
        }

        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        if ($terrenoId <= 0) {
            return 'Informe um terreno_id válido.';
        }

        $terreno = Terreno::find($terrenoId);
        if (!$terreno) {
            return "Terreno {$terrenoId} não encontrado.";
        }

        if (Gate::denies('view', $terreno)) {
            return "Acesso negado ao terreno {$terrenoId}.";
        }

        $result = $this->scoringService->getScore($terreno, false);

        $factorsSummary = [];
        foreach ($result['factors'] as $key => $data) {
            $factorsSummary[$key] = [
                'score' => round($data['raw'] ?? 0, 2),
                'details' => $data['details'] ?? '',
                'stage' => $data['stage'] ?? '',
                'assigned' => $data['assigned'] ?? '',
                'days_since_update' => $data['days_since_update'] ?? '',
            ];
        }

        $payload = [
            'terreno_id' => $terreno->id,
            'terreno_nome' => $terreno->nome,
            'score' => $result['score'],
            'tier' => $result['tier'],
            'tier_explicacao' => match ($result['tier']) {
                'alta_prioridade' => 'Terreno com alta probabilidade de sucesso e valor significativo.',
                'media' => 'Terreno com potencial médio, acompanhamento recomendado.',
                'atencao' => 'Terreno necessita atenção — há fatores de risco.',
                'baixa' => 'Terreno com baixo score — avaliar se mantém no pipeline.',
                'sem_classificacao' => 'Terreno encerrado ou sem dados suficientes.',
                default => '',
            },
            'factors' => $factorsSummary,
            'version' => $result['version'],
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar score.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()->required(),
        ];
    }
}
