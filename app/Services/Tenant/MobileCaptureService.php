<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Exceptions\MobileCaptureConflictException;
use App\Models\Tenant\MobileCapture;
use App\Models\Tenant\User;
use App\Repositories\Tenant\DocumentoRepository;
use App\Repositories\Tenant\MobileCaptureRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileCaptureService
{
    private const STORAGE_DISK = 's3';

    /** @var list<string> */
    private const TERRAIN_FIELDS = [
        'nome', 'responsavel_id', 'endereco', 'corretor_id', 'estado', 'cidade_code',
        'polygon_coords', 'static_map_url', 'regional_id', 'cep', 'bairro', 'observacoes',
        'valor', 'zona', 'distrito', 'operacao_urbana', 'data_apresentacao',
        'data_negociacao', 'data_opcao', 'data_descarte', 'data_contrato', 'comprador_id',
    ];

    public function __construct(
        private readonly MobileCaptureRepository $repository,
        private readonly TerrenoService $terrenoService,
        private readonly DocumentoRepository $documentoRepository,
    ) {}

    /** @param array<string, mixed> $data */
    public function createOrUpdate(User $user, array $data): MobileCapture
    {
        $existing = $this->repository->findExisting($user, (string) $data['client_id']);
        if ($existing) {
            return $existing->load('attachments', 'terreno');
        }

        return $this->repository->create([
            'client_id' => $data['client_id'],
            'user_id' => $user->id,
            'version' => 1,
            'status' => 'draft',
            'payload' => $data['payload'] ?? [],
            'latitude' => data_get($data, 'location.latitude'),
            'longitude' => data_get($data, 'location.longitude'),
            'accuracy' => data_get($data, 'location.accuracy'),
            'captured_at' => data_get($data, 'location.captured_at'),
        ])->load('attachments', 'terreno');
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, string $clientId, array $data): MobileCapture
    {
        $capture = $this->repository->findForUser($user, $clientId);
        $this->assertVersion($capture, (int) $data['base_version']);
        if ($capture->status !== 'draft') {
            return $capture;
        }

        return $this->repository->update($capture, [
            'version' => $capture->version + 1,
            'payload' => array_merge($capture->payload ?? [], $data['payload'] ?? []),
            'latitude' => data_get($data, 'location.latitude', $capture->latitude),
            'longitude' => data_get($data, 'location.longitude', $capture->longitude),
            'accuracy' => data_get($data, 'location.accuracy', $capture->accuracy),
            'captured_at' => data_get($data, 'location.captured_at', $capture->captured_at),
        ]);
    }

    public function upload(User $user, string $clientId, UploadedFile $file): MobileCapture
    {
        $capture = $this->repository->findForUser($user, $clientId);
        if ($capture->status !== 'draft') {
            return $capture;
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        if ($capture->attachments()->where('checksum', $checksum)->exists()) {
            return $capture->load('attachments', 'terreno');
        }

        $path = $file->storeAs(
            'mobile-captures/'.$capture->client_id,
            Str::uuid().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.($file->guessExtension() ?: 'bin'),
            self::STORAGE_DISK,
        );

        $capture->attachments()->create([
            'created_by' => $user->id,
            'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'file_path' => $path,
            'disk' => self::STORAGE_DISK,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => $checksum,
            'status' => 'uploaded',
        ]);

        return $capture->load('attachments', 'terreno');
    }

    public function commit(User $user, string $clientId, int $baseVersion): MobileCapture
    {
        return DB::transaction(function () use ($user, $clientId, $baseVersion): MobileCapture {
            $capture = MobileCapture::query()
                ->where('user_id', $user->id)
                ->where('client_id', $clientId)
                ->lockForUpdate()
                ->with('attachments')
                ->firstOrFail();

            if ($capture->status === 'committed') {
                return $capture->load('terreno', 'attachments');
            }
            $this->assertVersion($capture, $baseVersion);

            $payload = is_array($capture->payload) ? $capture->payload : [];
            $terrainData = Arr::only($payload, self::TERRAIN_FIELDS);
            if (empty($terrainData['nome'])) {
                throw new ValidationException(
                    validator([], ['nome' => ['required']]),
                );
            }
            $terreno = $this->terrenoService->create($terrainData, $user);

            foreach ($capture->attachments as $attachment) {
                if ($attachment->status !== 'uploaded' || ! Storage::disk($attachment->disk)->exists($attachment->file_path)) {
                    continue;
                }
                $this->documentoRepository->create([
                    'terreno_id' => $terreno->id,
                    'nome' => $attachment->original_name,
                    'tipo' => str_starts_with((string) $attachment->mime_type, 'audio/') ? 'outros' : 'outros',
                    'categoria' => null,
                    'descricao' => 'Anexo capturado em campo.',
                    'file_path' => $attachment->file_path,
                    'tamanho' => $attachment->size,
                    'status' => 'pendente',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
                $attachment->update(['status' => 'committed']);
            }

            return $this->repository->update($capture, [
                'status' => 'committed',
                'terreno_id' => $terreno->id,
                'committed_at' => now(),
                'version' => $capture->version + 1,
            ]);
        });
    }

    public function status(User $user, string $clientId): MobileCapture
    {
        return $this->repository->findForUser($user, $clientId);
    }

    private function assertVersion(MobileCapture $capture, int $baseVersion): void
    {
        if ($capture->version === $baseVersion) {
            return;
        }

        throw new MobileCaptureConflictException([
            'client_id' => $capture->client_id,
            'current_version' => (int) $capture->version,
            'current_status' => $capture->status,
            'payload' => $capture->payload,
        ]);
    }
}
