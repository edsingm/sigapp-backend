<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Documento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/** @mixin Documento */
class DocumentoResource extends JsonResource
{
    /**
     * Transformar o recurso em um array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Documento) {
            throw new LogicException('DocumentoResource requer um model Documento.');
        }

        $documento = $this->resource;

        return [
            'id' => $this->id,
            'terreno_id' => $this->terreno_id,
            'nome' => $this->nome,
            'tipo' => $this->tipo,
            'tipo_label' => $this->tipo_label,
            'categoria' => $this->categoria,
            'categoria_label' => $this->categoria_label,
            'descricao' => $this->descricao,
            'view_url' => url("/api/v1/documentos/{$this->id}/view"),
            'download_url' => url("/api/v1/documentos/{$this->id}/download"),
            'tamanho' => $this->tamanho,
            'tamanho_formatado' => $this->formatFileSize($this->tamanho),
            'status' => $this->status,
            'status_label' => $this->status_label,
            'terreno' => $this->whenLoaded('terreno', fn () => [
                'id' => $this->terreno->id,
                'nome' => $this->terreno->nome,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
            ]),
            'latest_analysis' => $this->when(
                $documento->relationLoaded('analyses'),
                function () use ($documento): ?array {
                    $analysis = $documento->analyses->sortByDesc('id')->first();
                    if ($analysis === null) {
                        return null;
                    }

                    $fields = is_array($analysis->extracted_fields) ? $analysis->extracted_fields : [];

                    return [
                        'id' => $analysis->id,
                        'status' => $analysis->status,
                        'summary' => $fields['summary'] ?? null,
                        'confidence' => $analysis->confidence,
                        'completed_at' => $analysis->completed_at?->toIso8601String(),
                    ];
                }
            ),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Formata o tamanho do arquivo em formato legível
     */
    private function formatFileSize(?int $bytes): string
    {
        if (! $bytes) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
