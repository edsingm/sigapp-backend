<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Documento;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DocumentosTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Documentos de terrenos: se document_id for informado, retorna análise detalhada do documento (metadados, tipo, sugestão de ação); caso contrário, lista documentos filtráveis por terreno, tipo, categoria e status.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Documento::class)) {
            return 'Acesso negado: você não tem permissão para acessar documentos.';
        }

        $documentId = (int) ($request['document_id'] ?? 0);

        if ($documentId > 0) {
            return $this->analyzeDocument($documentId);
        }

        return $this->listDocumentos($request);
    }

    private function analyzeDocument(int $documentId): string
    {
        $documento = Documento::find($documentId);
        if (! $documento) {
            return "Documento {$documentId} não encontrado.";
        }

        if (Gate::denies('view', $documento->terreno)) {
            return "Acesso negado: você não tem permissão para visualizar o documento {$documentId}.";
        }

        $payload = [
            'id'              => $documento->id,
            'terreno_id'      => $documento->terreno_id,
            'nome'            => $documento->nome,
            'tipo'            => $documento->tipo,
            'tipo_label'      => $documento->tipo_label ?? $documento->tipo,
            'categoria'       => $documento->categoria,
            'categoria_label' => $documento->categoria_label ?? $documento->categoria,
            'descricao'       => $documento->descricao,
            'status'          => $documento->status,
            'status_label'    => $documento->status_label ?? $documento->status,
            'tamanho_bytes'   => (int) ($documento->tamanho ?? 0),
            'file_path'       => $documento->file_path,
            'ai_analysis'     => [
                'tipo_detectado' => $documento->tipo ?? 'desconhecido',
                'sugestao_acao'  => match ($documento->tipo ?? '') {
                    'matricula'          => 'Verificar se a matrícula está atualizada com a última transmissão.',
                    'escritura'          => 'Conferir dados do proprietário e área com o terreno.',
                    'iptu'               => 'Validar valor venal e área com matrícula.',
                    'planta'             => 'Analisar se a planta corresponde ao polígono do terreno.',
                    'laudo_ambiental'    => 'Verificar restrições e apontamentos.',
                    'contrato'           => 'Revisar cláusulas, prazos e valores.',
                    'procuracao'         => 'Verificar validade e poderes outorgados.',
                    'certidao_negativa'  => 'Confirmar que não há débitos ou impedimentos.',
                    default              => 'Documento sem classificação específica. Revisar conteúdo.',
                },
            ],
            'created_at' => optional($documento->created_at)?->toAtomString(),
            'updated_at' => optional($documento->updated_at)?->toAtomString(),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar análise do documento.';
    }

    private function listDocumentos(Request $request): string
    {
        $query = Documento::query()
            ->select(['id', 'terreno_id', 'nome', 'tipo', 'categoria', 'descricao', 'file_path', 'tamanho', 'status', 'created_at'])
            ->orderByDesc('created_at');

        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        if ($terrenoId > 0) {
            $query->where('terreno_id', $terrenoId);
        }

        $tipo = trim((string) ($request['tipo'] ?? ''));
        if ($tipo !== '') {
            $query->where('tipo', $tipo);
        }

        $categoria = trim((string) ($request['categoria'] ?? ''));
        if ($categoria !== '') {
            $query->where('categoria', $categoria);
        }

        $status = trim((string) ($request['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $limit = max(1, min((int) ($request['limit'] ?? 20), 100));
        $documentos = $query->limit($limit)->get();

        if ($documentos->isEmpty()) {
            $filtros = [];
            if ($terrenoId > 0) {
                $filtros[] = "terreno {$terrenoId}";
            }
            if ($tipo) {
                $filtros[] = "tipo={$tipo}";
            }
            if ($categoria) {
                $filtros[] = "categoria={$categoria}";
            }
            $msg = 'Nenhum documento encontrado';
            if (! empty($filtros)) {
                $msg .= ' para '.implode(', ', $filtros);
            }

            return $msg.'.';
        }

        $payload = [
            'total' => $documentos->count(),
            'items' => $documentos->map(static function (Documento $d): array {
                $bytes = (int) ($d->tamanho ?? 0);

                return [
                    'id'              => $d->id,
                    'terreno_id'      => $d->terreno_id,
                    'nome'            => $d->nome,
                    'tipo'            => $d->tipo,
                    'tipo_label'      => $d->tipo_label ?? $d->tipo,
                    'categoria'       => $d->categoria,
                    'categoria_label' => $d->categoria_label ?? $d->categoria,
                    'descricao'       => $d->descricao,
                    'tamanho_bytes'   => $bytes,
                    'tamanho_formatado' => self::formatBytes($bytes),
                    'status'          => $d->status,
                    'status_label'    => $d->status_label ?? $d->status,
                    'created_at'      => optional($d->created_at)?->toAtomString(),
                ];
            })->all(),
            'resumo' => [
                'por_status'    => $documentos->groupBy('status')->map(fn ($g) => $g->count())->toArray(),
                'por_tipo'      => $documentos->groupBy('tipo')->map(fn ($g) => $g->count())->toArray(),
                'por_categoria' => $documentos->groupBy('categoria')->map(fn ($g) => $g->count())->toArray(),
            ],
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar documentos.';
    }

    protected static function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));

        return round($bytes / pow(1024, $i), 2).' '.$units[$i];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'document_id' => $schema->integer()->description('Se informado, analisa o documento específico; caso contrário, lista documentos.'),
            'terreno_id'  => $schema->integer(),
            'tipo'        => $schema->string(),
            'categoria'   => $schema->string(),
            'status'      => $schema->string(),
            'limit'       => $schema->integer(),
        ];
    }
}
