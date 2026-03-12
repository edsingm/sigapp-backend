<?php

use App\Models\Tenant\Projeto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $terrenos = DB::table('terrenos')
            ->select(
                'id',
                'created_at',
                'updated_at',
                'data_descarte',
                'data_negociacao',
                'data_opcao',
                'data_contrato'
            )
            ->get();

        foreach ($terrenos as $terreno) {
            [$stage, $status] = match (true) {
                !empty($terreno->data_descarte) => ['captacao', 'descartado'],
                !empty($terreno->data_contrato) => ['negociacao_contrato', 'contrato_assinado'],
                !empty($terreno->data_opcao) => ['negociacao_contrato', 'proposta_emitida'],
                !empty($terreno->data_negociacao) => ['negociacao_contrato', 'em_negociacao'],
                default => ['captacao', 'novo_lead'],
            };

            DB::table('terrenos')
                ->where('id', $terreno->id)
                ->update([
                    'workflow_stage' => $stage,
                    'workflow_status_code' => $status,
                    'workflow_status_changed_at' => $terreno->updated_at ?? $terreno->created_at ?? now(),
                ]);
        }

        $terrenoIdsWithViabilidade = DB::table('viabilidades')
            ->select('terreno_id')
            ->distinct()
            ->pluck('terreno_id');

        foreach ($terrenoIdsWithViabilidade as $terrenoId) {
            $records = DB::table('viabilidades')
                ->where('terreno_id', $terrenoId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            foreach ($records as $index => $record) {
                DB::table('viabilidades')
                    ->where('id', $record->id)
                    ->update([
                        'version' => $index + 1,
                        'is_current' => $index === ($records->count() - 1),
                        'submitted_at' => $record->approval_requested_at ?? null,
                        'locked_at' => in_array($record->approval_status, ['aprovada', 'reprovada'], true)
                            ? ($record->approval_decided_at ?? $record->updated_at ?? now())
                            : null,
                    ]);
            }
        }

        $eligibleStatuses = [
            'viabilidade_em_elaboracao',
            'viabilidade_aguardando_aprovacao',
            'viabilidade_aprovada',
            'viabilidade_para_revisao',
            'aguardando_comite',
            'em_comite',
            'aprovado_comite',
            'aprovado_com_ressalvas',
            'em_negociacao',
            'proposta_emitida',
            'minuta_contratual',
            'contrato_em_assinatura',
            'contrato_assinado',
            'legalizacao_nao_iniciada',
            'legalizacao_em_andamento',
            'legalizacao_com_pendencias',
            'legalizacao_concluida',
            'pronto_para_registro',
            'registrado',
            'encerrado',
        ];

        $terrenos = DB::table('terrenos')
            ->whereIn('workflow_status_code', $eligibleStatuses)
            ->pluck('id');

        foreach ($terrenos as $terrenoId) {
            $exists = DB::table('projetos')->where('terreno_id', $terrenoId)->exists();

            if ($exists) {
                continue;
            }

            DB::table('projetos')->insert([
                'nome' => "Workspace Terreno #{$terrenoId}",
                'terreno_id' => $terrenoId,
                'status' => Projeto::STATUS_EM_VIABILIDADE,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
    }
};
