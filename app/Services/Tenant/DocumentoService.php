<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Documento;
use App\Models\Tenant\User;
use App\Repositories\Tenant\DocumentoRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentoService
{
    private const STORAGE_DISK = 'local';

    /**
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'kml',
        'kmz',
        'dwg',
    ];

    public function __construct(
        private readonly DocumentoRepository $documentoRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromUpload(array $data, UploadedFile $file, User $user): Documento
    {
        $extension = strtolower((string) ($file->guessExtension() ?? ''));
        if ($extension === '' || ! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'arquivo' => ['Tipo de conteúdo não reconhecido ou não permitido.'],
            ]);
        }

        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'documento';
        $safeBaseName = Str::limit($baseName, 80, '');
        $fileName = now()->format('YmdHis').'_'.Str::lower((string) Str::uuid()).'_'.$safeBaseName.'.'.$extension;
        $path = $file->storeAs('documentos', $fileName, self::STORAGE_DISK);
        $displayName = $data['nome'] ?? Str::limit($file->getClientOriginalName(), 200, '');

        $documento = $this->documentoRepository->create([
            'terreno_id' => $data['terreno_id'],
            'nome' => $displayName,
            'tipo' => $data['tipo'] ?? 'outros',
            'categoria' => $data['categoria'] ?? null,
            'descricao' => $data['descricao'] ?? null,
            'file_path' => $path,
            'tamanho' => $file->getSize(),
            'status' => 'pendente',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return $this->documentoRepository->findOrFail($documento->id, ['terreno:id,nome', 'createdBy:id,name']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Documento $documento, array $data, User $user): Documento
    {
        $data['updated_by'] = $user->id;

        return $this->documentoRepository->update($documento, $data);
    }

    public function delete(Documento $documento): bool
    {
        if ($documento->file_path && Storage::disk(self::STORAGE_DISK)->exists($documento->file_path)) {
            Storage::disk(self::STORAGE_DISK)->delete($documento->file_path);
        }

        return $this->documentoRepository->delete($documento);
    }

    public function storageDisk(): string
    {
        return self::STORAGE_DISK;
    }
}
