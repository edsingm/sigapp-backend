<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Viabilidade;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetViabilidadesTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Consulta viabilidades por terreno, status e aprovação.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        $status = trim((string) ($request['status'] ?? ''));
        $approvalStatus = trim((string) ($request['approval_status'] ?? ''));
        $somenteAtual = filter_var($request['somente_atual'] ?? false, FILTER_VALIDATE_BOOL);
        $limit = max(1, min((int) ($request['limit'] ?? 20), 100));

        $query = Viabilidade::query()
            ->with(['terreno:id,nome,endereco,cidade_code,estado'])
            ->select([
                'id',
                'terreno_id',
                'version',
                'is_current',
                'status',
                'approval_status',
                'approval_requested_at',
                'approval_decided_at',
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('updated_at');

        if ($terrenoId > 0) {
            $query->where('terreno_id', $terrenoId);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($approvalStatus !== '') {
            $query->where('approval_status', $approvalStatus);
        }

        if ($somenteAtual) {
            $query->where('is_current', true);
        }

        $viabilidades = $query->limit($limit)->get();

        if ($viabilidades->isEmpty()) {
            return 'Nenhuma viabilidade encontrada para os filtros informados.';
        }

        $payload = [
            'total' => $viabilidades->count(),
            'items' => $viabilidades->map(static function (Viabilidade $viabilidade): array {
                return [
                    'id' => $viabilidade->id,
                    'terreno_id' => $viabilidade->terreno_id,
                    'terreno' => $viabilidade->terreno ? [
                        'nome' => $viabilidade->terreno->nome,
                        'endereco' => $viabilidade->terreno->endereco,
                        'cidade_code' => $viabilidade->terreno->cidade_code,
                        'estado' => $viabilidade->terreno->estado,
                    ] : null,
                    'version' => $viabilidade->version,
                    'is_current' => $viabilidade->is_current,
                    'status' => $viabilidade->status,
                    'approval_status' => $viabilidade->approval_status,
                    'approval_requested_at' => optional($viabilidade->approval_requested_at)?->toAtomString(),
                    'approval_decided_at' => optional($viabilidade->approval_decided_at)?->toAtomString(),
                    'created_at' => optional($viabilidade->created_at)?->toAtomString(),
                    'updated_at' => optional($viabilidade->updated_at)?->toAtomString(),
                ];
            })->all(),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar viabilidades.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()->nullable(),
            'status' => $schema->string()->nullable(),
            'approval_status' => $schema->string()->nullable(),
            'somente_atual' => $schema->boolean()->nullable(),
            'limit' => $schema->integer()->nullable(),
        ];
    }
}
