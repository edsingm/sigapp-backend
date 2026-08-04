<?php

namespace App\Services\Ai\Tools;

use App\Exceptions\DocumentAnalysisUnsupportedException;
use App\Models\Tenant\DocumentAnalysis;
use App\Models\Tenant\Documento;
use App\Models\Tenant\User;
use App\Services\Tenant\DocumentAnalysisEligibility;
use App\Services\Tenant\DocumentIntelligenceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DocumentosTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Documentos de terrenos: com document_id retorna metadados e a análise de conteúdo do PDF (summary/campos) quando existir; se for PDF sem análise e o plano permitir, enfileira análise sob demanda. Sem document_id, lista documentos filtráveis.';
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
        $documento = Documento::query()->with(['terreno:id'])->find($documentId);
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
            'is_pdf' => app(DocumentAnalysisEligibility::class)->isPdfDocumento($documento),
            'analysis' => $this->resolveAnalysisPayload($documento),
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
                'disclaimer' => 'Heurística por tipo cadastrado — complementar à analysis de conteúdo quando existir.',
            ],
            'created_at' => optional($documento->created_at)?->toAtomString(),
            'updated_at' => optional($documento->updated_at)?->toAtomString(),
        ];

        return AiToolResponse::ok($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveAnalysisPayload(Documento $documento): ?array
    {
        $latest = $documento->analyses()->latest('id')->first();
        if ($latest instanceof DocumentAnalysis && in_array($latest->status, ['queued', 'running', 'completed'], true)) {
            return $this->mapAnalysis($latest);
        }

        // failed / ausente: tenta enfileirar nova análise (reprocessamento)
        $auth = app(AiToolAuth::class);
        if ($deny = $auth->ensureFeature(
            'documents.intelligence',
            'Plano sem análise inteligente de documentos.'
        )) {
            if ($latest instanceof DocumentAnalysis) {
                $mapped = $this->mapAnalysis($latest);
                $mapped['message'] = 'Análise de conteúdo indisponível no plano atual; exibindo última tentativa.';

                return $mapped;
            }

            return [
                'status' => 'unavailable',
                'message' => 'Análise de conteúdo indisponível no plano atual.',
            ];
        }

        $eligibility = app(DocumentAnalysisEligibility::class);
        if (! $eligibility->canAnalyzeOnDemand($documento)) {
            return [
                'status' => 'unsupported',
                'message' => 'Somente arquivos PDF podem ser analisados por conteúdo.',
            ];
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            return [
                'status' => 'unavailable',
                'message' => 'Usuário não autenticado para enfileirar análise.',
            ];
        }

        try {
            // force=true quando a última falhou, para não “grudar” no failed
            $force = $latest instanceof DocumentAnalysis && $latest->status === 'failed';
            $queued = app(DocumentIntelligenceService::class)->requestAnalysis($documento, $user, $force);

            return $this->mapAnalysis($queued);
        } catch (DocumentAnalysisUnsupportedException $exception) {
            return [
                'status' => 'unsupported',
                'message' => $exception->getMessage(),
            ];
        } catch (\Throwable $exception) {
            // Nunca derrubar o stream do chat por falha de análise/embedding/provider.
            return [
                'status' => 'failed',
                'message' => 'Não foi possível enfileirar ou concluir a análise neste momento. Tente POST /documentos/{id}/analysis ou consulte mais tarde.',
                'error_code' => class_basename($exception),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAnalysis(DocumentAnalysis $analysis): array
    {
        return [
            'id' => $analysis->id,
            'status' => $analysis->status,
            'provider' => $analysis->provider,
            'model' => $analysis->model,
            'confidence' => $analysis->confidence,
            'extracted_fields' => $analysis->extracted_fields,
            'summary' => is_array($analysis->extracted_fields)
                ? ($analysis->extracted_fields['summary'] ?? null)
                : null,
            'key_fields' => is_array($analysis->extracted_fields)
                ? ($analysis->extracted_fields['key_fields'] ?? null)
                : null,
            'limitations' => $analysis->limitations,
            'error_message' => $analysis->status === 'failed' ? $analysis->error_message : null,
            'completed_at' => optional($analysis->completed_at)?->toAtomString(),
        ];
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
