<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListTerrenosTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Lista terrenos com filtros opcionais para apoiar análise inicial.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $search = trim((string) ($request['search'] ?? ''));
        $workflowStage = trim((string) ($request['workflow_stage'] ?? ''));
        $workflowStatus = trim((string) ($request['workflow_status_code'] ?? ''));
        $cidadeCode = trim((string) ($request['cidade_code'] ?? ''));
        $limit = max(1, min((int) ($request['limit'] ?? 10), 50));

        $query = Terreno::query()
            ->with([
                'viabilidadeAtual:id,terreno_id,version,status,approval_status,updated_at',
            ])
            ->select([
                'id',
                'nome',
                'endereco',
                'cidade_code',
                'estado',
                'area_calculada',
                'valor',
                'workflow_stage',
                'workflow_status_code',
                'updated_at',
            ])
            ->orderByDesc('updated_at');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('nome', 'like', "%{$search}%")
                    ->orWhere('endereco', 'like', "%{$search}%");
            });
        }

        if ($workflowStage !== '') {
            $query->where('workflow_stage', $workflowStage);
        }

        if ($workflowStatus !== '') {
            $query->where('workflow_status_code', $workflowStatus);
        }

        if ($cidadeCode !== '') {
            $query->where('cidade_code', $cidadeCode);
        }

        $terrenos = $query->limit($limit)->get();

        if ($terrenos->isEmpty()) {
            return 'Nenhum terreno encontrado para os filtros informados.';
        }

        $payload = [
            'total' => $terrenos->count(),
            'items' => $terrenos->map(static function (Terreno $terreno): array {
                return [
                    'id' => $terreno->id,
                    'nome' => $terreno->nome,
                    'endereco' => $terreno->endereco,
                    'cidade_code' => $terreno->cidade_code,
                    'estado' => $terreno->estado,
                    'area_calculada' => $terreno->area_calculada,
                    'valor' => $terreno->valor,
                    'workflow_stage' => $terreno->workflow_stage,
                    'workflow_status_code' => $terreno->workflow_status_code,
                    'updated_at' => optional($terreno->updated_at)?->toAtomString(),
                    'viabilidade_atual' => $terreno->viabilidadeAtual ? [
                        'id' => $terreno->viabilidadeAtual->id,
                        'version' => $terreno->viabilidadeAtual->version,
                        'status' => $terreno->viabilidadeAtual->status,
                        'approval_status' => $terreno->viabilidadeAtual->approval_status,
                        'updated_at' => optional($terreno->viabilidadeAtual->updated_at)?->toAtomString(),
                    ] : null,
                ];
            })->all(),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar a lista de terrenos.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->nullable(),
            'workflow_stage' => $schema->string()->nullable(),
            'workflow_status_code' => $schema->string()->nullable(),
            'cidade_code' => $schema->string()->nullable(),
            'limit' => $schema->integer()->nullable(),
        ];
    }
}
