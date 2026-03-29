<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetTerrenoDetailsTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retorna detalhes de um terreno específico pelo id, incluindo status e contexto atual.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $terrenoId = (int) ($request['terreno_id'] ?? $request['id'] ?? 0);
        $includeViabilidades = filter_var($request['include_viabilidades'] ?? false, FILTER_VALIDATE_BOOL);

        if ($terrenoId <= 0) {
            return 'Informe um terreno_id válido.';
        }

        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar terrenos.';
        }

        $terreno = Terreno::query()
            ->with([
                'viabilidadeAtual:id,terreno_id,version,status,approval_status,updated_at',
                'negociacaoAtual:id,terreno_id,status,proposal_value,started_at,closed_at',
                'contratoAtual:id,terreno_id,contract_type,contract_number,status,signed_at',
                'projetos:id,terreno_id,nome,status,created_at',
            ])
            ->withCount(['documentos', 'contatos', 'proprietarios', 'viabilidades', 'projetos'])
            ->select([
                'id',
                'nome',
                'endereco',
                'cep',
                'bairro',
                'cidade_code',
                'estado',
                'distrito',
                'zona',
                'operacao_urbana',
                'area_calculada',
                'valor',
                'workflow_stage',
                'workflow_status_code',
                'workflow_reason_code',
                'workflow_reason_notes',
                'observacoes',
                'data_apresentacao',
                'data_negociacao',
                'data_opcao',
                'data_contrato',
                'data_descarte',
                'updated_at',
            ])
            ->find($terrenoId);

        if (! $terreno) {
            return "Terreno {$terrenoId} não encontrado.";
        }

        if (Gate::denies('view', $terreno)) {
            return "Acesso negado: você não tem permissão para visualizar o terreno {$terrenoId}.";
        }

        $payload = [
            'id' => $terreno->id,
            'nome' => $terreno->nome,
            'endereco' => $terreno->endereco,
            'cep' => $terreno->cep,
            'bairro' => $terreno->bairro,
            'cidade_code' => $terreno->cidade_code,
            'estado' => $terreno->estado,
            'distrito' => $terreno->distrito,
            'zona' => $terreno->zona,
            'operacao_urbana' => $terreno->operacao_urbana,
            'area_calculada' => $terreno->area_calculada,
            'valor' => $terreno->valor,
            'workflow_stage' => $terreno->workflow_stage,
            'workflow_status_code' => $terreno->workflow_status_code,
            'workflow_reason_code' => $terreno->workflow_reason_code,
            'workflow_reason_notes' => $terreno->workflow_reason_notes,
            'observacoes' => $terreno->observacoes,
            'datas' => [
                'apresentacao' => optional($terreno->data_apresentacao)?->toDateString(),
                'negociacao' => optional($terreno->data_negociacao)?->toDateString(),
                'opcao' => optional($terreno->data_opcao)?->toDateString(),
                'contrato' => optional($terreno->data_contrato)?->toDateString(),
                'descarte' => optional($terreno->data_descarte)?->toDateString(),
                'updated_at' => optional($terreno->updated_at)?->toAtomString(),
            ],
            'totais' => [
                'documentos' => $terreno->documentos_count,
                'contatos' => $terreno->contatos_count,
                'proprietarios' => $terreno->proprietarios_count,
                'viabilidades' => $terreno->viabilidades_count,
                'projetos' => $terreno->projetos_count,
            ],
            'viabilidade_atual' => $terreno->viabilidadeAtual ? [
                'id' => $terreno->viabilidadeAtual->id,
                'version' => $terreno->viabilidadeAtual->version,
                'status' => $terreno->viabilidadeAtual->status,
                'approval_status' => $terreno->viabilidadeAtual->approval_status,
                'updated_at' => optional($terreno->viabilidadeAtual->updated_at)?->toAtomString(),
            ] : null,
            'negociacao_atual' => $terreno->negociacaoAtual ? [
                'status' => $terreno->negociacaoAtual->status,
                'proposal_value' => $terreno->negociacaoAtual->proposal_value,
                'started_at' => optional($terreno->negociacaoAtual->started_at)?->toAtomString(),
                'closed_at' => optional($terreno->negociacaoAtual->closed_at)?->toAtomString(),
            ] : null,
            'contrato_atual' => $terreno->contratoAtual ? [
                'contract_type' => $terreno->contratoAtual->contract_type,
                'contract_number' => $terreno->contratoAtual->contract_number,
                'status' => $terreno->contratoAtual->status,
                'signed_at' => optional($terreno->contratoAtual->signed_at)?->toAtomString(),
            ] : null,
            'projetos' => $terreno->projetos
                ->map(static fn ($projeto): array => [
                    'id' => $projeto->id,
                    'nome' => $projeto->nome,
                    'status' => $projeto->status,
                    'created_at' => optional($projeto->created_at)?->toAtomString(),
                ])->all(),
        ];

        if ($includeViabilidades) {
            $payload['ultimas_viabilidades'] = $terreno->viabilidades()
                ->select(['id', 'terreno_id', 'version', 'is_current', 'status', 'approval_status', 'updated_at'])
                ->orderByDesc('version')
                ->limit(5)
                ->get()
                ->map(static fn ($viabilidade): array => [
                    'id' => $viabilidade->id,
                    'version' => $viabilidade->version,
                    'is_current' => $viabilidade->is_current,
                    'status' => $viabilidade->status,
                    'approval_status' => $viabilidade->approval_status,
                    'updated_at' => optional($viabilidade->updated_at)?->toAtomString(),
                ])->all();
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar detalhes do terreno.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()->required(),
            'include_viabilidades' => $schema->boolean(),
        ];
    }
}
