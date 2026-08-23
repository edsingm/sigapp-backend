<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Models\Tenant\AiDocumentChunk;
use App\Models\Tenant\AiDocumentEmbedding;
use App\Models\Tenant\AiRequestLog;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Contrato;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Produto;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use Illuminate\Console\Command;

class PurgeSoftDeletesCommand extends Command
{
    protected $signature = 'privacy:purge-soft-deletes {--days= : Sobrescreve a retenção em dias}';

    protected $description = 'Remove soft deletes e logs de IA mais antigos que a retenção configurada';

    public function handle(): int
    {
        $days = max(1, $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('privacy.soft_delete_retention_days', 90));
        $cutoff = now()->subDays($days);
        $aiCutoff = now()->subDays(max(1, (int) config('privacy.ai_log_retention_days', 90)));
        $removed = 0;

        Tenant::query()
            ->where('status', Tenant::STATUS_ACTIVE)
            ->where('database_created', true)
            ->whereNull('wiped_at')
            ->select(['id'])
            ->toBase()
            ->chunkById(50, function ($rows) use ($cutoff, $aiCutoff, &$removed): void {
                foreach ($rows as $row) {
                    $tenant = Tenant::query()->findOrFail((string) $row->id);

                    $tenant->run(function () use ($cutoff, $aiCutoff, &$removed): void {
                        $removed += Terreno::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
                        $removed += Proprietario::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
                        $removed += Viabilidade::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
                        $removed += Negociacao::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
                        $removed += Contrato::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
                        $removed += Legalizacao::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
                        $removed += Projeto::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
                        $removed += ComiteRevisao::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
                        $removed += Produto::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();

                        $removed += AiRequestLog::query()
                            ->where('created_at', '<', $aiCutoff)
                            ->forceDelete();

                        $orphanIds = AiDocumentChunk::query()
                            ->whereDoesntHave('documento')
                            ->pluck('id');

                        if ($orphanIds->isNotEmpty()) {
                            AiDocumentEmbedding::query()->whereIn('chunk_id', $orphanIds)->delete();
                            $removed += AiDocumentChunk::query()->whereIn('id', $orphanIds)->delete();
                        }
                    });
                }
            });

        $this->info('Registros removidos: '.$removed);

        return self::SUCCESS;
    }
}
