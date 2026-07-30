<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant\TerrenoImport;
use App\Repositories\Tenant\TerrenoImportRepository;
use App\Services\Tenant\TerrenoImportValidationService;
use App\Services\Tenant\TerrenoSpreadsheetService;
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
#[Timeout(300)]
#[Backoff([30, 120])]
#[Queue('exports')]
class ValidateTerrenoImportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 660;

    public function __construct(public readonly int $importId) {}

    public function uniqueId(): string
    {
        return sprintf('%s:%d', tenant()?->getTenantKey() ?? 'central', $this->importId);
    }

    public function handle(
        TerrenoImportRepository $repository,
        TerrenoSpreadsheetService $spreadsheets,
        TerrenoImportValidationService $validation,
    ): void {
        $import = $repository->claimQueued($this->importId);
        if (! $import instanceof TerrenoImport) {
            return;
        }
        $temporaryPath = null;

        try {
            $diskName = $import->storage_disk;
            $storagePath = $import->storage_path;
            if (! is_string($diskName) || ! is_string($storagePath)) {
                throw new RuntimeException('Arquivo da importação não encontrado.');
            }
            $temporaryPath = tempnam(sys_get_temp_dir(), 'sigapp-terreno-import-');
            if ($temporaryPath === false) {
                throw new RuntimeException('Não foi possível criar arquivo temporário.');
            }
            $contents = Storage::disk($diskName)->get($storagePath);
            file_put_contents($temporaryPath, $contents);
            $sourceRows = $spreadsheets->read($temporaryPath);
            $rows = $validation->validate($sourceRows, $import->id);
            $invalid = count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'invalid'));
            $repository->replaceRows($import, $rows);
            $repository->markValidated($import, count($rows), count($rows) - $invalid, $invalid);
        } catch (Throwable $exception) {
            $repository->releaseForRetry($this->importId);

            throw $exception;
        } finally {
            if (is_string($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        try {
            if (Storage::disk($diskName)->delete($storagePath)) {
                $repository->clearStorage($this->importId);
            } else {
                Log::warning('Arquivo validado de importação de terrenos permaneceu no storage.', [
                    'import_id' => $this->importId,
                    'storage_path' => $storagePath,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Falha ao remover arquivo validado de importação de terrenos.', [
                'import_id' => $this->importId,
                'storage_path' => $storagePath,
                'exception' => $exception::class,
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $repository = app(TerrenoImportRepository::class);
        $import = $repository->findById($this->importId);
        if ($import instanceof TerrenoImport && is_string($import->storage_disk) && is_string($import->storage_path)) {
            Storage::disk($import->storage_disk)->delete($import->storage_path);
        }
        $repository->markFailed($this->importId, 'TERRAIN_IMPORT_VALIDATION_FAILED', $exception->getMessage());
        $repository->clearStorage($this->importId);
        Log::error('ValidateTerrenoImportJob falhou definitivamente.', [
            'import_id' => $this->importId,
            'exception' => $exception::class,
        ]);
    }
}
