<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\TerrenoPolygonImportStatus;
use App\Enums\TerrenoPolygonStatus;
use App\Exceptions\TerrenoImportException;
use App\Jobs\ParseTerrenoPolygonImportJob;
use App\Models\Tenant\TerrenoPendingPolygon;
use App\Models\Tenant\TerrenoPolygonImport;
use App\Models\Tenant\User;
use App\Repositories\Tenant\TerrenoPolygonImportRepository;
use App\Repositories\Tenant\TerrenoRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TerrenoPolygonImportService
{
    public function __construct(
        private readonly TerrenoPolygonImportRepository $repository,
        private readonly TerrenoRepository $terrains,
        private readonly StorageQuotaService $storageQuota,
    ) {}

    /** @param list<UploadedFile> $files */
    public function create(User $user, string $idempotencyKey, array $files): TerrenoPolygonImport
    {
        $existing = $this->repository->findByIdempotency($user, $idempotencyKey);
        if ($existing instanceof TerrenoPolygonImport) {
            return $existing;
        }

        $import = $this->repository->createOrFind($user, $idempotencyKey, [
            'status' => TerrenoPolygonImportStatus::QUEUED,
            'progress' => 0,
            'total_files' => count($files),
            'requested_at' => now(),
        ]);
        if (! $import->wasRecentlyCreated) {
            return $import;
        }

        try {
            foreach ($files as $file) {
                $extension = mb_strtolower($file->getClientOriginalExtension());
                if (! in_array($extension, ['kml', 'kmz'], true)) {
                    throw new RuntimeException('Envie somente arquivos KML ou KMZ.');
                }
                $diskName = 's3';
                $path = 'imports/terrenos/polygons/'.Str::uuid().'.'.$extension;
                $contents = file_get_contents($file->getRealPath());
                if ($contents === false || ! Storage::disk($diskName)->put($path, $contents)) {
                    throw new RuntimeException('Não foi possível armazenar um dos arquivos geográficos.');
                }
                $this->storageQuota->commitFile(
                    $diskName,
                    $path,
                    fn (int $size) => $this->repository->createFile($import, [
                        'file_name' => $file->getClientOriginalName(),
                        'storage_disk' => $diskName,
                        'storage_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'size' => $size,
                        'checksum' => hash('sha256', $contents),
                        'status' => 'queued',
                    ]),
                );
            }
        } catch (\Throwable $exception) {
            foreach ($this->repository->filesForImport($import) as $storedFile) {
                if (is_string($storedFile->storage_disk) && is_string($storedFile->storage_path)) {
                    Storage::disk($storedFile->storage_disk)->delete($storedFile->storage_path);
                }
                $this->repository->clearFileStorage($storedFile);
            }
            $this->repository->markFailed($import->id, 'POLYGON_IMPORT_UPLOAD_FAILED', $exception->getMessage());

            throw $exception;
        }

        ParseTerrenoPolygonImportJob::dispatch($import->id);

        return $import->refresh();
    }

    public function find(User $user, int $id): TerrenoPolygonImport
    {
        return $this->repository->findForUser($user, $id);
    }

    /** @return Collection<int, TerrenoPendingPolygon> */
    public function polygons(float $minLng, float $minLat, float $maxLng, float $maxLat, int $limit): Collection
    {
        return $this->repository->inBoundingBox($minLng, $minLat, $maxLng, $maxLat, $limit);
    }

    public function link(int $polygonId, int $terrainId, User $user): TerrenoPendingPolygon
    {
        return DB::transaction(function () use ($polygonId, $terrainId, $user): TerrenoPendingPolygon {
            $polygon = $this->repository->findPolygonForUpdate($polygonId);
            if ($polygon->status === TerrenoPolygonStatus::LINKED) {
                if ($polygon->terreno_id === $terrainId) {
                    return $polygon;
                }
                throw new TerrenoImportException(
                    'POLYGON_ALREADY_LINKED',
                    'O polígono já está associado a outro terreno.',
                );
            }

            $terrain = $this->terrains->findForUpdate($terrainId);
            if (is_array($terrain->polygon_coords) && $terrain->polygon_coords !== []) {
                throw new TerrenoImportException(
                    'TERRAIN_ALREADY_HAS_POLYGON',
                    'O terreno já possui um polígono e não pode ser sobrescrito.',
                );
            }
            $this->terrains->update($terrain, [
                'polygon_coords' => $polygon->polygon_coords,
                'updated_by' => $user->id,
            ]);

            return $this->repository->markLinked($polygon, $terrain->id, $user->id);
        });
    }

    public function discard(int $polygonId): void
    {
        if (! $this->repository->deletePending($polygonId)) {
            throw new TerrenoImportException(
                'PENDING_POLYGON_NOT_FOUND',
                'O polígono pendente não foi encontrado.',
                404,
            );
        }
    }
}
