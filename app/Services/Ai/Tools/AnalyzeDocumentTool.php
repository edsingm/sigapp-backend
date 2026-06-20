<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Documento;
use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AnalyzeDocumentTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Extrai informações e analisa um documento específico por ID. Retorna metadados, tipo detectado e conteúdo.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para analisar documentos.';
        }

        $documentId = (int) ($request['document_id'] ?? 0);
        if ($documentId <= 0) {
            return 'Informe um document_id válido.';
        }

        $documento = Documento::find($documentId);
        if (! $documento) {
            return "Documento {$documentId} não encontrado.";
        }

        if (Gate::denies('view', $documento->terreno)) {
            return "Acesso negado: você não tem permissão para visualizar o documento {$documentId}.";
        }

        $payload = [
            'id' => $documento->id,
            'terreno_id' => $documento->terreno_id,
            'nome' => $documento->nome,
            'tipo' => $documento->tipo,
            'tipo_label' => $documento->tipo_label ?? $documento->tipo,
            'categoria' => $documento->categoria,
            'categoria_label' => $documento->categoria_label ?? $documento->categoria,
            'descricao' => $documento->descricao,
            'status' => $documento->status,
            'status_label' => $documento->status_label ?? $documento->status,
            'tamanho_bytes' => (int) ($documento->tamanho ?? 0),
            'file_path' => $documento->file_path,
            'ai_analysis' => [
                'tipo_detectado' => $documento->tipo ?? 'desconhecido',
                'sugestao_acao' => match ($documento->tipo ?? '') {
                    'matricula' => 'Verificar se a matrícula está atualizada com a última transmissão.',
                    'escritura' => 'Conferir dados do proprietário e área com o terreno.',
                    'iptu' => 'Validar valor venal e área com matrícula.',
                    'planta' => 'Analisar se a planta corresponde ao polígono do terreno.',
                    'laudo_ambiental' => 'Verificar restrições e apontamentos.',
                    'contrato' => 'Revisar cláusulas, prazos e valores.',
                    'procuracao' => 'Verificar validade e poderes outorgados.',
                    'certidao_negativa' => 'Confirmar que não há débitos ou impedimentos.',
                    default => 'Documento sem classificação específica. Revisar conteúdo.',
                },
            ],
            'created_at' => optional($documento->created_at)?->toAtomString(),
            'updated_at' => optional($documento->updated_at)?->toAtomString(),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar análise do documento.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'document_id' => $schema->integer()->required(),
        ];
    }
}
