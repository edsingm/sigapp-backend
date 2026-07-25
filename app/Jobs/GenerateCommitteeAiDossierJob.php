<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant\ComiteRevisao;
use App\Services\Tenant\CommitteeAiDossierService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Queue('ai')]
class GenerateCommitteeAiDossierJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var array<int, int> */
    public array $backoff = [60, 180, 300];

    public int $uniqueFor = 660;

    public function __construct(
        public readonly int $reviewId,
        public readonly ?int $userId = null,
    ) {}

    public function uniqueId(): string
    {
        return sprintf('%s:%d', tenant()?->getTenantKey() ?? 'central', $this->reviewId);
    }

    public function handle(CommitteeAiDossierService $service): void
    {
        $review = ComiteRevisao::query()->find($this->reviewId);

        if (! $review instanceof ComiteRevisao) {
            Log::warning('GenerateCommitteeAiDossierJob: comitê não encontrado', [
                'comite_revisao_id' => $this->reviewId,
            ]);

            return;
        }

        $service->generate($review, $this->userId);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateCommitteeAiDossierJob falhou definitivamente', [
            'comite_revisao_id' => $this->reviewId,
            'error' => $exception->getMessage(),
        ]);
    }
}
