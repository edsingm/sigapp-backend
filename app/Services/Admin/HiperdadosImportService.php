<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\HiperdadosImportStatus;
use App\Exceptions\HiperdadosImportException;
use App\Jobs\CommitHiperdadosImportJob;
use App\Jobs\FetchHiperdadosImportJob;
use App\Models\Central\HiperdadosImport;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Repositories\Central\HiperdadosImportRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HiperdadosImportService
{
    private const STORAGE_DIR = 'imports/hiperdados';

    /** Quantidade de terrenos enriquecidos por execução de job (timeout Redis 660s). */
    private const ENRICH_BATCH_SIZE = 20;

    public function __construct(
        private readonly HiperdadosImportRepository $repository,
        private readonly HiperdadosPortalScrapeService $scrapeService,
        private readonly HiperdadosTerrenoCommitService $commitService,
    ) {}

    /**
     * @return array{import: HiperdadosImport}
     */
    public function start(User $admin, string $username, string $password, ?int $limit = null): array
    {
        $username = trim($username);
        $password = trim($password);

        if ($username === '' || $password === '') {
            throw new HiperdadosImportException('HIPERDADOS_CREDENTIALS_REQUIRED', 'Informe usuário e senha do portal.', 422);
        }

        $payload = json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_THROW_ON_ERROR);

        $import = $this->repository->create([
            'uuid' => (string) Str::uuid(),
            'status' => HiperdadosImportStatus::Queued,
            'created_by' => $admin->id,
            'portal_username' => $username,
            'credentials_encrypted' => Crypt::encryptString($payload),
            'limit_count' => $limit !== null && $limit > 0 ? $limit : null,
            'total_count' => 0,
            'processed_count' => 0,
            'failed_count' => 0,
            'imported_count' => 0,
        ]);

        FetchHiperdadosImportJob::dispatch($import->id);

        return ['import' => $import->fresh(['creator', 'tenant']) ?? $import];
    }

    /**
     * Processa um lote da extração/enriquecimento.
     *
     * @return bool true se ainda há lotes a processar (re-dispatch)
     */
    public function processFetch(int $importId): bool
    {
        $import = $this->repository->findById($importId);
        if (! $import instanceof HiperdadosImport) {
            return false;
        }

        if ($import->status !== HiperdadosImportStatus::Queued
            && $import->status !== HiperdadosImportStatus::Fetching) {
            return false;
        }

        if ($import->status === HiperdadosImportStatus::Queued) {
            $this->repository->markFetching($import);
            $import = $import->fresh() ?? $import;
        }

        $disk = (string) ($import->storage_disk ?: config('filesystems.default', 'local'));
        $workPath = self::STORAGE_DIR.'/'.$import->uuid.'.work.json';
        $finalPath = self::STORAGE_DIR.'/'.$import->uuid.'.json';

        try {
            $credentials = $this->decryptCredentials($import);
            $work = $this->loadWorkState($disk, $workPath);

            if ($work === null) {
                $lista = $this->scrapeService->extractList(
                    $credentials['username'],
                    $credentials['password'],
                    $import->limit_count,
                );

                $work = [
                    'lista' => $lista,
                    'enriquecidos' => [],
                    'failures' => [],
                    'next_index' => 0,
                ];

                $this->repository->updateProgress($import, 0, 0, count($lista));
                $this->saveWorkState($disk, $workPath, $work);
                $this->repository->update($import, [
                    'storage_disk' => $disk,
                    'total_count' => count($lista),
                ]);
            }

            /** @var list<array<string, mixed>> $lista */
            $lista = $work['lista'];
            $nextIndex = (int) $work['next_index'];
            $total = count($lista);

            if ($nextIndex >= $total) {
                $this->finalizeFetch($import, $disk, $finalPath, $workPath, $work);

                return false;
            }

            $slice = array_slice($lista, $nextIndex, self::ENRICH_BATCH_SIZE);
            $batch = $this->scrapeService->enrichBatch(
                $credentials['username'],
                $credentials['password'],
                $slice,
            );

            $work['enriquecidos'] = array_merge($work['enriquecidos'], $batch['items']);
            $work['failures'] = array_merge($work['failures'], $batch['failures']);
            $work['next_index'] = $nextIndex + count($slice);

            $this->saveWorkState($disk, $workPath, $work);
            $this->repository->updateProgress(
                $import,
                count($work['enriquecidos']),
                count($work['failures']),
                $total,
            );

            if ($work['next_index'] >= $total) {
                $this->finalizeFetch($import->fresh() ?? $import, $disk, $finalPath, $workPath, $work);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            $this->repository->markFailed($import->fresh() ?? $import, $e->getMessage());

            throw $e;
        }
    }

    /**
     * @return array{import: HiperdadosImport}
     */
    public function commit(HiperdadosImport $import, string $tenantId, User $_admin): array
    {
        if (! $import->status->canCommit()) {
            throw new HiperdadosImportException(
                'HIPERDADOS_IMPORT_NOT_READY',
                'A importação só pode ser confirmada quando o status for ready.',
                409
            );
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant instanceof Tenant) {
            throw new HiperdadosImportException('TENANT_NOT_FOUND', 'Tenant não encontrado.', 404);
        }

        if (! (bool) $tenant->getAttribute('database_created')) {
            throw new HiperdadosImportException(
                'TENANT_DATABASE_NOT_READY',
                'O banco do tenant ainda não foi provisionado.',
                409
            );
        }

        if ($import->storage_disk === null || $import->storage_path === null) {
            throw new HiperdadosImportException(
                'HIPERDADOS_PAYLOAD_MISSING',
                'Arquivo de dados da importação não encontrado.',
                409
            );
        }

        $this->repository->markCommitting($import, (string) $tenant->id);
        CommitHiperdadosImportJob::dispatch($import->id);

        return ['import' => $import->fresh(['creator', 'tenant']) ?? $import];
    }

    public function processCommit(int $importId): void
    {
        $import = $this->repository->findById($importId);
        if (! $import instanceof HiperdadosImport) {
            return;
        }

        if ($import->status !== HiperdadosImportStatus::Committing) {
            return;
        }

        $tenantId = $import->tenant_id;
        if ($tenantId === null || $tenantId === '') {
            $this->repository->markFailed($import, 'Tenant não informado para o commit.');

            return;
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant instanceof Tenant) {
            $this->repository->markFailed($import, 'Tenant não encontrado.');

            return;
        }

        try {
            $terrenos = $this->loadPayload($import);

            $result = $tenant->run(function () use ($terrenos): array {
                return $this->commitService->commit($terrenos);
            });

            $this->repository->markCompleted($import->fresh() ?? $import, $result['imported'], [
                'imported' => $result['imported'],
                'cidades_nao_resolvidas' => $result['cidades_nao_resolvidas'],
                'tenant_id' => (string) $tenant->id,
                'tenant_name' => (string) $tenant->getAttribute('name'),
            ]);
        } catch (Throwable $e) {
            $this->repository->markFailed($import->fresh() ?? $import, $e->getMessage());

            throw $e;
        }
    }

    /**
     * @return array{
     *     total: int,
     *     offset: int,
     *     limit: int,
     *     items: list<array<string, mixed>>
     * }
     */
    public function preview(HiperdadosImport $import, int $offset = 0, int $limit = 50): array
    {
        $terrenos = $this->loadPayload($import);
        $offset = max(0, $offset);
        $limit = max(1, min(200, $limit));

        return [
            'total' => count($terrenos),
            'offset' => $offset,
            'limit' => $limit,
            'items' => array_slice($terrenos, $offset, $limit),
        ];
    }

    public function findByUuid(string $uuid): ?HiperdadosImport
    {
        return $this->repository->findByUuid($uuid);
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginateForAdmin($perPage);
    }

    /**
     * @param  array{
     *     lista: list<array<string, mixed>>,
     *     enriquecidos: list<array<string, mixed>>,
     *     failures: list<array{id: mixed, erro: string}>,
     *     next_index: int
     * }  $work
     */
    private function finalizeFetch(
        HiperdadosImport $import,
        string $disk,
        string $finalPath,
        string $workPath,
        array $work,
    ): void {
        $terrenos = $work['enriquecidos'];
        $json = json_encode($terrenos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Falha ao serializar terrenos extraídos.');
        }

        Storage::disk($disk)->put($finalPath, $json);

        if (Storage::disk($disk)->exists($workPath)) {
            Storage::disk($disk)->delete($workPath);
        }

        $summary = [
            'failures' => array_slice($work['failures'], 0, 100),
            'sample' => array_map(
                static fn (array $item): array => [
                    'id' => $item['id'] ?? null,
                    'nome' => $item['nome'] ?? null,
                    'status' => $item['status'] ?? (is_array($item['ficha'] ?? null) ? ($item['ficha']['status_portal'] ?? null) : null),
                    'gestor' => $item['gestor'] ?? (is_array($item['ficha'] ?? null) ? ($item['ficha']['gestor'] ?? null) : null),
                ],
                array_slice($terrenos, 0, 20)
            ),
        ];

        $this->repository->markReady(
            $import,
            count($terrenos),
            count($terrenos),
            count($work['failures']),
            $disk,
            $finalPath,
            $summary,
        );
    }

    /**
     * @return array{
     *     lista: list<array<string, mixed>>,
     *     enriquecidos: list<array<string, mixed>>,
     *     failures: list<array{id: mixed, erro: string}>,
     *     next_index: int
     * }|null
     */
    private function loadWorkState(string $disk, string $path): ?array
    {
        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) Storage::disk($disk)->get($path), true);
        if (! is_array($decoded) || ! isset($decoded['lista'], $decoded['enriquecidos'], $decoded['next_index'])) {
            return null;
        }

        return [
            'lista' => is_array($decoded['lista']) ? array_values($decoded['lista']) : [],
            'enriquecidos' => is_array($decoded['enriquecidos']) ? array_values($decoded['enriquecidos']) : [],
            'failures' => is_array($decoded['failures'] ?? null) ? array_values($decoded['failures']) : [],
            'next_index' => (int) $decoded['next_index'],
        ];
    }

    /**
     * @param  array<string, mixed>  $work
     */
    private function saveWorkState(string $disk, string $path, array $work): void
    {
        $json = json_encode($work, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Falha ao salvar estado intermediário da importação.');
        }

        Storage::disk($disk)->put($path, $json);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadPayload(HiperdadosImport $import): array
    {
        $disk = $import->storage_disk;
        $path = $import->storage_path;

        if (! is_string($disk) || ! is_string($path) || $path === '') {
            throw new HiperdadosImportException(
                'HIPERDADOS_PAYLOAD_MISSING',
                'Arquivo de dados da importação não encontrado.',
                409
            );
        }

        if (! Storage::disk($disk)->exists($path)) {
            throw new HiperdadosImportException(
                'HIPERDADOS_PAYLOAD_MISSING',
                'Arquivo de dados da importação não encontrado no storage.',
                409
            );
        }

        $raw = Storage::disk($disk)->get($path);
        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            throw new HiperdadosImportException(
                'HIPERDADOS_PAYLOAD_INVALID',
                'JSON da importação está inválido.',
                422
            );
        }

        /** @var list<array<string, mixed>> $decoded */
        return $decoded;
    }

    /**
     * @return array{username: string, password: string}
     */
    private function decryptCredentials(HiperdadosImport $import): array
    {
        $encrypted = $import->credentials_encrypted;
        if (! is_string($encrypted) || $encrypted === '') {
            throw new RuntimeException('Credenciais do portal não estão disponíveis para esta importação.');
        }

        $decoded = json_decode(Crypt::decryptString($encrypted), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Credenciais do portal corrompidas.');
        }

        $username = trim((string) ($decoded['username'] ?? ''));
        $password = (string) ($decoded['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new RuntimeException('Credenciais do portal incompletas.');
        }

        return ['username' => $username, 'password' => $password];
    }
}
