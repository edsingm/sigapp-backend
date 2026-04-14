<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $statusMap = [
            'novo_lead' => 'em_analise',
            'em_qualificacao' => 'em_analise',
            'standby' => 'em_analise',
            'qualificacao_em_andamento' => 'em_analise',
            'qualificacao_concluida' => 'aguardando_viabilidade',
            'qualificacao_reprovada' => 'em_analise',
            'viabilidade_nao_iniciada' => 'aguardando_viabilidade',
            'viabilidade_em_elaboracao' => 'aguardando_viabilidade',
            'viabilidade_aguardando_aprovacao' => 'aguardando_viabilidade',
            'viabilidade_reprovada' => 'em_analise',
            'viabilidade_para_revisao' => 'aguardando_viabilidade',
            'viabilidade_aprovada' => 'viabilidade_aprovada',
            'aguardando_comite' => 'aguardando_comite',
            'em_comite' => 'aguardando_comite',
            'aprovado_comite' => 'negociacao_minuta',
            'aprovado_com_ressalvas' => 'negociacao_minuta',
            'reprovado_comite' => 'em_analise',
            'em_negociacao' => 'negociacao_minuta',
            'proposta_emitida' => 'negociacao_minuta',
            'minuta_contratual' => 'negociacao_minuta',
            'contrato_em_assinatura' => 'negociacao_minuta',
            'negociacao_perdida' => 'em_analise',
            'contrato_cancelado' => 'em_analise',
            'contrato_assinado' => 'contrato_assinado',
            'legalizacao_nao_iniciada' => 'legalizando',
            'legalizacao_em_andamento' => 'legalizando',
            'legalizacao_com_pendencias' => 'legalizando',
            'legalizacao_concluida' => 'legalizado_finalizado',
            'pronto_para_registro' => 'legalizado_finalizado',
            'registrado' => 'legalizado_finalizado',
            'encerrado' => 'legalizado_finalizado',
            'descartado' => 'descartado',
            'arquivado' => 'arquivado',
        ];

        foreach (DB::table('terrenos')->select('id', 'workflow_status_code')->cursor() as $terreno) {
            $status = $statusMap[$terreno->workflow_status_code] ?? null;

            if (! $status) {
                continue;
            }

            DB::table('terrenos')
                ->where('id', $terreno->id)
                ->update([
                    'workflow_status_code' => $status,
                    'workflow_stage' => $this->stageFor($status),
                    'workflow_status_changed_at' => now(),
                ]);
        }

        DB::table('projetos')
            ->where('status', 'pronto_para_registro')
            ->update([
                'status' => 'finalizado',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Sem rollback automático: o mapeamento antigo era muitos-para-um.
    }

    private function stageFor(string $status): string
    {
        return match ($status) {
            'em_analise' => 'captacao',
            'aguardando_viabilidade', 'viabilidade_aprovada' => 'viabilidade',
            'aguardando_comite' => 'comite',
            'negociacao_minuta', 'contrato_assinado' => 'negociacao_contrato',
            'legalizando' => 'legalizacao',
            default => 'encerramento',
        };
    }
};
