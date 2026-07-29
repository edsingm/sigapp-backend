<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Documento;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
        if ($deny = app(AiToolAuth::class)->ensureViewAny(
            Documento::class,
            'Acesso negado: você não tem permissão para acessar documentos.'
        )) {
            return $deny;
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
            return AiToolResponse::empty("Documento {$documentId} não encontrado.");
        }

        if ($deny = app(AiToolAuth::class)->ensureView(
            $documento->terreno,
            "Acesso negado: você não tem permissão para visualizar o documento {$documentId}."
        )) {
            return $deny;
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
            'heuristica_tipo' => [
                'source' => 'rule',
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
                'disclaimer' => 'Sugestão por regra de tipo de arquivo — não é análise de conteúdo por IA.',
            ],
            'created_at' => optional($documento->created_at)?->toAtomString(),
            'updated_at' => optional($documento->updated_at)?->toAtomString(),
        ];

        return AiToolResponse::ok($payload);
    }

    private function listDocumentos(Request $request): string
    {
        $query = Documento::query()
            ->with('terreno:id')
            ->select(['id', 'terreno_id', 'nome', 'tipo', 'categoria', 'descricao', 'tamanho', 'status', 'created_at'])
            ->orderByDesc('created_at');

        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        if ($terrenoId > 0) {
            $terrenoOrDeny = app(AiToolAuth::class)->ensureTerrenoView($terrenoId);
            if (is_string($terrenoOrDeny)) {
                return $terrenoOrDeny;
            }
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

        $limit = AiToolResponse::clampLimit($request['limit'] ?? 20, default: 20, max: 50);
        $total = (int) $query->count();
        $documentos = $query->limit($limit)->get();

        if ($terrenoId <= 0) {
            $documentos = app(AiToolAuth::class)->filterByView(
                $documentos,
                static fn ($d) => $d->terreno
            );
            $total = $documentos->count();
        }

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

            return AiToolResponse::empty(
                $msg.'.',
                ['items' => [], 'meta' => AiToolResponse::listMeta($total, 0, $limit)]
            );
        }

        $items = $documentos->map(static function (Documento $d): array {
            $bytes = (int) ($d->tamanho ?? 0);

            return [
                'id' => $d->id,
                'terreno_id' => $d->terreno_id,
                'nome' => $d->nome,
                'tipo' => $d->tipo,
                'tipo_label' => $d->tipo_label ?? $d->tipo,
                'categoria' => $d->categoria,
                'categoria_label' => $d->categoria_label ?? $d->categoria,
                'descricao' => $d->descricao,
                'tamanho_bytes' => $bytes,
                'tamanho_formatado' => self::formatBytes($bytes),
                'status' => $d->status,
                'status_label' => $d->status_label ?? $d->status,
                'created_at' => optional($d->created_at)?->toAtomString(),
            ];
        })->all();

        return AiToolResponse::ok([
            'items' => $items,
            'resumo' => [
                'por_status' => $documentos->groupBy('status')->map(fn ($g) => $g->count())->toArray(),
                'por_tipo' => $documentos->groupBy('tipo')->map(fn ($g) => $g->count())->toArray(),
                'por_categoria' => $documentos->groupBy('categoria')->map(fn ($g) => $g->count())->toArray(),
            ],
            'meta' => AiToolResponse::listMeta($total, count($items), $limit),
        ]);
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
            'document_id' => $schema->integer()
                ->description('Se informado, analisa o documento específico; caso contrário, lista documentos.'),
            'terreno_id' => $schema->integer()
                ->description('Filtra documentos de um terreno.'),
            'tipo' => $schema->string()
                ->description('Tipo do documento (ex.: matricula, escritura).'),
            'categoria' => $schema->string()
                ->description('Categoria do documento.'),
            'status' => $schema->string()
                ->description('Status do documento.'),
            'limit' => $schema->integer()
                ->description('Máximo de itens na listagem (padrão 20).')
                ->min(1),
        ];
    }
}
