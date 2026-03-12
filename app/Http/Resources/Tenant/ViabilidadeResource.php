<?php
namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ViabilidadeResource extends JsonResource
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
            'version' => $this->version,
            'is_current' => $this->is_current,
            'parceria_vgv' => (float) $this->parceria_vgv,
            'compra_terreno' => (float) $this->compra_terreno,
            'infra_nao_incidente' => (float) $this->infra_nao_incidente,
            'porcentagem_lote_proprietario' => (float) $this->porcentagem_lote_proprietario,
            'prazo_obra' => (int) $this->prazo_obra,
            'pis_cofins' => (float) $this->pis_cofins,
            'iss' => (float) $this->iss,
            'outros_impostos' => (float) $this->outros_impostos,
            'comissao' => (float) $this->comissao,
            'incorporacao' => (float) $this->incorporacao,
            'area_comum' => (float) $this->area_comum,
            'contrapartidas' => (float) $this->contrapartidas,
            'canteiro_mensal' => (float) $this->canteiro_mensal,
            'mo_administrativa' => (float) $this->mo_administrativa,
            'seguros' => (float) $this->seguros,
            'assistencia_tecnica' => (float) $this->assistencia_tecnica,
            'despesas_comerciais' => (float) $this->despesas_comerciais,
            'marketing' => (float) $this->marketing,
            'itbi_iptu' => (float) $this->itbi_iptu,
            'registro' => (float) $this->registro,
            'medicao_contratacao' => (float) $this->medicao_contratacao,
            'contratos_cef' => (float) $this->contratos_cef,
            'produtos_cef' => (float) $this->produtos_cef,
            'outras_despesas_financeiras' => (float) $this->outras_despesas_financeiras,
            'despesas_onerosas_bancos' => (float) $this->despesas_onerosas_bancos,
            'status' => $this->status,
            'approval_status' => $this->approval_status ?? ($this->status === 'ativo' ? 'aprovada' : 'pendente'),
            'approval_requested_at' => $this->approval_requested_at?->toIso8601String(),
            'approval_decided_at' => $this->approval_decided_at?->toIso8601String(),
            'approval_notes' => $this->approval_notes,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'locked_at' => $this->locked_at?->toIso8601String(),
            'resultados_dre' => $this->resultados_dre,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relacionamentos
            'terreno' => $this->whenLoaded('terreno', fn() => [
                'id' => $this->terreno->id,
                'nome' => $this->terreno->nome,
            ]),
            'created_by_user' => $this->relationLoaded('createdBy') && $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null,
            'user' => $this->relationLoaded('createdBy') && $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null,
            'approval_decided_by_user' => $this->relationLoaded('approvalDecidedBy') && $this->approvalDecidedBy ? [
                'id' => $this->approvalDecidedBy->id,
                'name' => $this->approvalDecidedBy->name,
            ] : null,
            'sections' => $this->whenLoaded('secoes', fn () => $this->secoes->map(fn ($secao) => [
                'id' => $secao->id,
                'section_code' => $secao->section_code,
                'section_name' => $secao->section_name,
                'content_json' => $secao->content_json,
                'status' => $secao->status,
            ])->values()),
            'approvals' => $this->whenLoaded('aprovacoes', fn () => $this->aprovacoes->map(fn ($approval) => [
                'id' => $approval->id,
                'decision' => $approval->decision,
                'comments' => $approval->comments,
                'created_at' => $approval->created_at?->toIso8601String(),
                'user' => $approval->relationLoaded('user') && $approval->user ? [
                    'id' => $approval->user->id,
                    'name' => $approval->user->name,
                ] : null,
            ])->values()),
        ];
    }
}




?>
