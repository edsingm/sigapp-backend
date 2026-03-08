<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terreno_id' => $this->terreno_id,
            'nome' => $this->nome,
            'tipo' => $this->tipo,
            'tipo_label' => $this->tipo_label,
            'categoria' => $this->categoria,
            'categoria_label' => $this->categoria_label,
            'descricao' => $this->descricao,
            'url' => $this->url,
            'tamanho' => $this->tamanho,
            'tamanho_formatado' => $this->formatFileSize($this->tamanho),
            'status' => $this->status,
            'status_label' => $this->status_label,
            'terreno' => $this->whenLoaded('terreno', fn() => [
                'id' => $this->terreno->id,
                'nome' => $this->terreno->nome,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn() => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'updated_by' => $this->whenLoaded('updatedBy', fn() => [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Formata o tamanho do arquivo em formato legível
     */
    private function formatFileSize(?int $bytes): string
    {
        if (!$bytes)
            return '0 B';

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
