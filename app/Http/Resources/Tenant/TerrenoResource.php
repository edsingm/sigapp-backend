<?php

namespace App\Http\Resources\Tenant;

use App\Services\Tenant\LandWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerrenoResource extends JsonResource
{
    /**
     * Transformar o recurso em um array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'responsavel_id' => $this->responsavel_id,
            'responsavel' => new UserResource($this->whenLoaded('responsavel')),
            'endereco' => $this->endereco,
            'corretor_id' => $this->corretor_id,
            'corretor_externo' => new CorretorExternoResource($this->whenLoaded('corretorExterno')),
            'estado' => $this->estado,
            'cidade_code' => $this->cidade_code,
            'cidade_nome' => $this->whenLoaded('cidade', fn () => $this->cidade?->city),
            'polygon_coords' => $this->polygon_coords,
            'static_map_url' => $this->static_map_url,
            'area_calculada' => $this->area_calculada ? (float) $this->area_calculada : null,
            'workflow_stage' => $this->workflow_stage,
            'workflow_status_code' => $this->workflow_status_code,
            'workflow_status_label' => LandWorkflowService::statuses()[$this->workflow_status_code]['label'] ?? null,
            'workflow_status_changed_at' => $this->workflow_status_changed_at?->toIso8601String(),
            'workflow_reason_code' => $this->workflow_reason_code,
            'workflow_reason_notes' => $this->workflow_reason_notes,
            'qualification_data' => $this->qualification_data,
            'qualification_completed_at' => $this->qualification_completed_at?->toIso8601String(),
            'checklist' => app(LandWorkflowService::class)->checklist($this->resource),
            'regional_id' => $this->regional_id,
            'regional' => new RegionalResource($this->whenLoaded('regional')),
            'cep' => $this->cep,
            'bairro' => $this->bairro,
            'observacoes' => $this->observacoes,
            'valor' => $this->valor ? (float) $this->valor : null,
            'zona' => $this->zona,
            'distrito' => $this->distrito,
            'operacao_urbana' => $this->operacao_urbana,
            'data_apresentacao' => $this->data_apresentacao?->format('Y-m-d'),
            'data_negociacao' => $this->data_negociacao?->format('Y-m-d'),
            'data_opcao' => $this->data_opcao?->format('Y-m-d'),
            'data_descarte' => $this->data_descarte?->format('Y-m-d'),
            'data_contrato' => $this->data_contrato?->format('Y-m-d'),
            'comprador_id' => $this->comprador_id,
            'comprador' => $this->whenLoaded('comprador', function () {
                return [
                    'id' => $this->comprador->id,
                    'name' => $this->comprador->name,
                    'email' => $this->comprador->email,
                ];
            }),
            'tipo_captacao' => $this->tipo_captacao,
            'created_by' => $this->created_by,
            'created_by_user' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }),
            'updated_by' => $this->updated_by,
            'updated_by_user' => $this->whenLoaded('updatedBy', function () {
                return [
                    'id' => $this->updatedBy->id,
                    'name' => $this->updatedBy->name,
                ];
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Campos calculados
            'valor_formatado' => $this->valor ? 'R$ '.number_format($this->valor, 2, ',', '.') : null,
            'area_formatada' => $this->area_calculada ? number_format($this->area_calculada, 2, ',', '.').' m²' : null,
            'endereco_completo' => $this->endereco.($this->relationLoaded('cidade') && $this->cidade ? ', '.$this->cidade->city : '').($this->estado ? ' - '.$this->estado : ''),

            // Relacionamentos adicionais
            'proprietarios' => ProprietarioResource::collection($this->whenLoaded('proprietarios')),
            'contatos' => TerrenoContatoResource::collection($this->whenLoaded('contatos')),
            'terreno_produtos' => TerrenoProdutoResource::collection($this->whenLoaded('terrenoProdutos')),
            'cidade_dados' => new CidadeResource($this->whenLoaded('cidade')),
            'documentos' => DocumentoResource::collection($this->whenLoaded('documentos')),
            'viabilidades' => ViabilidadeResource::collection($this->whenLoaded('viabilidades')),
            'informacoes' => TerrenoInfoResource::collection($this->whenLoaded('informacoes')),
            'terreno_produtos_count' => isset($this->terreno_produtos_count) ? (int) $this->terreno_produtos_count : null,
            'total_unidades' => isset($this->total_unidades) ? (int) $this->total_unidades : null,
            'vgv_total' => isset($this->vgv_total) ? (float) $this->vgv_total : null,
            'documentos_count' => isset($this->documentos_count) ? (int) $this->documentos_count : null,
            'viabilidades_count' => isset($this->viabilidades_count) ? (int) $this->viabilidades_count : null,
            'terreno_infos_count' => isset($this->terreno_infos_count) ? (int) $this->terreno_infos_count : null,
            'viabilidade_atual' => new ViabilidadeResource($this->whenLoaded('viabilidadeAtual')),
            'comite_atual' => new ComiteRevisaoResource($this->whenLoaded('comiteAtual')),
            'negociacao_atual' => new NegociacaoResource($this->whenLoaded('negociacaoAtual')),
            'contrato_atual' => new ContratoResource($this->whenLoaded('contratoAtual')),
            'legalizacao' => new LegalizacaoResource($this->whenLoaded('legalizacao')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'status_history' => StatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'activities' => EntityActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
