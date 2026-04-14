<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Documento;
use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetDocumentosTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Lista documentos anexados a um terreno, filtráveis por tipo, categoria ou status.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar documentos.';
        }

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
            })->all(),
            'resumo' => [
                'por_status' => $documentos->groupBy('status')->map->count()->toArray(),
                'por_tipo' => $documentos->groupBy('tipo')->map->count()->toArray(),
                'por_categoria' => $documentos->groupBy('categoria')->map->count()->toArray(),
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
            'terreno_id' => $schema->integer(),
            'tipo' => $schema->string(),
            'categoria' => $schema->string(),
            'status' => $schema->string(),
            'limit' => $schema->integer(),
        ];
    }
}
