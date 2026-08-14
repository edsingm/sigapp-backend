<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\StorageQuotaExceededException;
use App\Models\Tenant\TenantExportGeneration;
use App\Models\Tenant\User;
use App\Repositories\Tenant\TenantExportGenerationRepository;
use App\Services\Privacy\PrivacySubjectService;
use App\Services\Tenant\StorageQuotaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Tries(3)]
#[Timeout(120)]
#[Backoff([30, 120])]
#[Queue('exports')]
class GenerateSubjectPortabilityJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $generationId) {}

    public int $uniqueFor = 180;

    public function uniqueId(): string
    {
        return sprintf('%s:subject-portability:%d', tenant()?->getTenantKey() ?? 'central', $this->generationId);
    }

    public function handle(
        TenantExportGenerationRepository $repository,
        PrivacySubjectService $privacy,
        StorageQuotaService $storageQuota,
    ): void {
        $generation = $repository->claimQueued($this->generationId);
        if (! $generation instanceof TenantExportGeneration) {
            return;
        }

        try {
            $user = $generation->requester;
            if (! $user instanceof User) {
                throw new RuntimeException('Solicitante da portabilidade não encontrado.');
            }

            $fileName = sprintf(
                'portabilidade-titular-%d-%s.json',
                (int) $user->getKey(),
                now()->format('Y-m-d'),
            );
            $path = 'exports/'.$generation->id.'/'.$fileName;
            $encoded = json_encode(
                $privacy->exportPayload($user),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
            );

            Storage::disk('s3')->put($path, $encoded, ['visibility' => 'private']);

            $storageQuota->commitFile(
                's3',
                $path,
                function (int $size) use ($repository, $generation, $path, $fileName): void {
                    $repository->markCompleted($generation, [
                        'storage_disk' => 's3',
                        'storage_path' => $path,
                        'file_name' => $fileName,
                        'mime_type' => 'application/json',
                        'size' => $size,
                    ]);
                },
            );
        } catch (StorageQuotaExceededException) {
            $repository->markFailed($this->generationId);
        } catch (Throwable $exception) {
            $repository->releaseForRetry($this->generationId);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        app(TenantExportGenerationRepository::class)->markFailed($this->generationId);

        Log::error('GenerateSubjectPortabilityJob falhou definitivamente.', [
            'generation_id' => $this->generationId,
            'exception' => $exception::class,
        ]);
    }
}
