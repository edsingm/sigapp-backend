<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\StorageQuotaExceededException;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\TenantExportGeneration;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Repositories\Tenant\TenantExportGenerationRepository;
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
use Throwable;

#[Tries(3)]
#[Timeout(300)]
#[Backoff([30, 120])]
#[Queue('exports')]
class GenerateTenantPortabilityJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $generationId) {}

    public int $uniqueFor = 360;

    public function uniqueId(): string
    {
        return sprintf('%s:tenant-portability:%d', tenant()?->getTenantKey() ?? 'central', $this->generationId);
    }

    public function handle(
        TenantExportGenerationRepository $repository,
        StorageQuotaService $storageQuota,
    ): void {
        $generation = $repository->claimQueued($this->generationId);
        if (! $generation instanceof TenantExportGeneration) {
            return;
        }

        try {
            $fileName = 'portabilidade-workspace-'.now()->format('Y-m-d').'.json';
            $path = 'exports/'.$generation->id.'/'.$fileName;
            $encoded = json_encode($this->payload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

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

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $tenant = tenant();

        return [
            'exported_at' => now()->toIso8601String(),
            'tenant' => $tenant ? [
                'id' => $tenant->getTenantKey(),
                'name' => $tenant->getAttribute('name'),
                'slug' => $tenant->getAttribute('slug'),
            ] : null,
            'users' => User::query()->get(['id', 'name', 'email', 'status', 'created_at'])->all(),
            'terrenos' => Terreno::query()->limit(2000)->get(['id', 'nome', 'cidade_code', 'estado', 'created_at'])->all(),
            'proprietarios' => Proprietario::query()->limit(2000)->get(['id', 'terreno_id', 'nome', 'tipo_pessoa'])->all(),
            'counts' => [
                'users' => User::query()->count(),
                'terrenos' => Terreno::query()->count(),
                'proprietarios' => Proprietario::query()->count(),
            ],
        ];
    }

    public function failed(Throwable $exception): void
    {
        app(TenantExportGenerationRepository::class)->markFailed($this->generationId);

        Log::error('GenerateTenantPortabilityJob falhou definitivamente.', [
            'generation_id' => $this->generationId,
            'exception' => $exception::class,
        ]);
    }
}
