<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant\TerrenoPolygonImport;
use App\Repositories\Tenant\TerrenoPolygonImportRepository;
use App\Services\Tenant\KmzParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
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
class ParseTerrenoPolygonImportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 660;

    public function __construct(public readonly int $importId) {}

    public function uniqueId(): string
    {
        return sprintf('%s:%d', tenant()?->getTenantKey() ?? 'central', $this->importId);
    }

    public function handle(TerrenoPolygonImportRepository $repository, KmzParserService $parser): void
    {
        $import = $repository->claimQueued($this->importId);
        if (! $import instanceof TerrenoPolygonImport) {
            return;
        }

        try {
            $processed = $import->processed_files;
            foreach ($import->files as $file) {
                if ($file->status !== 'queued') {
                    continue;
                }
                $temporaryPath = null;
                try {
                    $temporaryPath = tempnam(sys_get_temp_dir(), 'sigapp-polygon-import-');
                    if ($temporaryPath === false) {
                        throw new RuntimeException('Não foi possível criar arquivo temporário.');
                    }
                    if (! is_string($file->storage_disk) || ! is_string($file->storage_path)) {
                        throw new RuntimeException('Arquivo geográfico não encontrado.');
                    }
                    file_put_contents($temporaryPath, Storage::disk($file->storage_disk)->get($file->storage_path));
                    $uploaded = new UploadedFile($temporaryPath, $file->file_name, $file->mime_type, null, true);
                    $geometries = $parser->parseMany($uploaded);
                    $created = 0;
                    foreach ($geometries as $geometry) {
                        $polygon = $repository->createPolygon([
                            'terreno_polygon_import_id' => $import->id,
                            'terreno_polygon_import_file_id' => $file->id,
                            'source_entry' => is_string($geometry['source_entry'])
                                ? mb_substr($geometry['source_entry'], 0, 255)
                                : null,
                            'placemark_name' => is_string($geometry['placemark_name'])
                                ? mb_substr($geometry['placemark_name'], 0, 255)
                                : null,
                            'geometry_index' => $geometry['geometry_index'],
                            'polygon_coords' => $geometry['coords'],
                            'geometry_hash' => $geometry['geometry_hash'],
                            ...$geometry['bounds'],
                            'status' => 'pending',
                        ]);
                        if ($polygon !== null) {
                            $created++;
                        }
                    }
                    Storage::disk($file->storage_disk)->delete($file->storage_path);
                    $repository->markFileProcessed($file, $created);
                } catch (Throwable $exception) {
                    if (is_string($file->storage_disk) && is_string($file->storage_path)) {
                        Storage::disk($file->storage_disk)->delete($file->storage_path);
                    }
                    $repository->markFileFailed($file, $exception->getMessage());
                } finally {
                    if (is_string($temporaryPath)) {
                        @unlink($temporaryPath);
                    }
                }
                $processed++;
                $repository->updateProgress($import->id, $processed, $import->total_files);
            }
            $repository->complete($import);
        } catch (Throwable $exception) {
            $repository->releaseForRetry($import->id);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $repository = app(TerrenoPolygonImportRepository::class);
        $import = $repository->findWithFiles($this->importId);
        if ($import instanceof TerrenoPolygonImport) {
            foreach ($import->files as $file) {
                if (is_string($file->storage_disk) && is_string($file->storage_path)) {
                    Storage::disk($file->storage_disk)->delete($file->storage_path);
                }
                $repository->clearFileStorage($file);
            }
        }
        $repository->markFailed(
            $this->importId,
            'POLYGON_IMPORT_PROCESSING_FAILED',
            $exception->getMessage(),
        );
        Log::error('ParseTerrenoPolygonImportJob falhou definitivamente.', [
            'import_id' => $this->importId,
            'exception' => $exception::class,
        ]);
    }
}
