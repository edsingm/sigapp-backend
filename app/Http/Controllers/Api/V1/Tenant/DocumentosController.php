<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListDocumentosRequest;
use App\Http\Requests\Tenant\StoreDocumentoRequest;
use App\Http\Requests\Tenant\UpdateDocumentoRequest;
use App\Http\Resources\Tenant\DocumentoResource;
use App\Models\Tenant\Documento;
use App\Repositories\Tenant\DocumentoRepository;
use App\Services\Tenant\DocumentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentosController extends Controller
{
    public function __construct(
        private readonly DocumentoRepository $documentoRepository,
        private readonly DocumentoService $documentoService,
    ) {}

    /**
     * Listar documentos.
     */
    public function index(ListDocumentosRequest $request): JsonResponse
    {
        $tenantId = tenant('id') ?? 'central';
        $filters = $request->validated();
        $cacheKey = "tenant:{$tenantId}:documentos:index:".md5(json_encode($filters));

        return Cache::tags(["tenant:{$tenantId}:documentos"])->remember($cacheKey, now()->addMinutes(30), function () use ($filters) {
            $documentos = $this->documentoRepository->paginate($filters);

            return response()->json([
                'data' => DocumentoResource::collection($documentos->items()),
                'meta' => [
                    'current_page' => $documentos->currentPage(),
                    'last_page' => $documentos->lastPage(),
                    'per_page' => $documentos->perPage(),
                    'total' => $documentos->total(),
                ],
            ]);
        });
    }

    /**
     * Armazenar um novo documento.
     */
    public function store(StoreDocumentoRequest $request): JsonResponse
    {
        $documento = $this->documentoService->createFromUpload(
            $request->validated(),
            $request->file('arquivo'),
            $request->user()
        );

        return response()->json([
            'message' => 'Documento enviado com sucesso.',
            'data' => new DocumentoResource($documento),
        ], 201);
    }

    /**
     * Exibir os detalhes de um documento específico.
     */
    public function show(int $id): JsonResponse
    {
        $documento = $this->documentoRepository->findOrFail($id, ['terreno:id,nome', 'createdBy:id,name', 'updatedBy:id,name']);
        Gate::authorize('view', $documento);

        return response()->json([
            'data' => new DocumentoResource($documento),
        ]);
    }

    /**
     * Atualizar um documento existente.
     */
    public function update(UpdateDocumentoRequest $request, int $id): JsonResponse
    {
        $documento = $this->documentoRepository->findOrFail($id);
        $documento = $this->documentoService->update($documento, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Documento atualizado com sucesso.',
            'data' => new DocumentoResource($documento),
        ]);
    }

    /**
     * Excluir um documento.
     */
    public function destroy(int $id): JsonResponse
    {
        $documento = $this->documentoRepository->findOrFail($id);
        Gate::authorize('delete', $documento);

        $this->documentoService->delete($documento);

        return response()->json([
            'message' => 'Documento excluído com sucesso.',
        ]);
    }

    /**
     * Baixar o arquivo do documento.
     */
    public function download(int $id)
    {
        $documento = $this->documentoRepository->findOrFail($id);
        Gate::authorize('view', $documento);

        if (! $documento->file_path || ! Storage::disk($this->documentoService->storageDisk())->exists($documento->file_path)) {
            return response()->json([
                'message' => 'Arquivo não encontrado.',
            ], 404);
        }

        $downloadName = $documento->nome;
        $extension = pathinfo($documento->file_path, PATHINFO_EXTENSION);

        if ($extension !== '' && pathinfo($downloadName, PATHINFO_EXTENSION) === '') {
            $downloadName .= '.'.$extension;
        }

        return response()->download(
            Storage::disk($this->documentoService->storageDisk())->path($documento->file_path),
            $downloadName
        );
    }

    /**
     * Visualizar o arquivo do documento no navegador.
     */
    public function view(int $id)
    {
        $documento = $this->documentoRepository->findOrFail($id);
        Gate::authorize('view', $documento);

        if (! $documento->file_path || ! Storage::disk($this->documentoService->storageDisk())->exists($documento->file_path)) {
            return response()->json([
                'message' => 'Arquivo não encontrado.',
            ], 404);
        }

        $filename = $documento->nome;
        $extension = pathinfo($documento->file_path, PATHINFO_EXTENSION);

        if ($extension !== '' && pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.'.$extension;
        }

        return response()->file(
            Storage::disk($this->documentoService->storageDisk())->path($documento->file_path),
            [
                'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
            ]
        );
    }

    /**
     * Listar os tipos de documentos disponíveis.
     */
    public function tipos(): JsonResponse
    {
        Gate::authorize('viewAny', Documento::class);

        return response()->json([
            'data' => [
                ['value' => 'escritura', 'label' => 'Escritura'],
                ['value' => 'matricula', 'label' => 'Matrícula'],
                ['value' => 'certidao_negativa', 'label' => 'Certidão Negativa'],
                ['value' => 'iptu', 'label' => 'IPTU'],
                ['value' => 'planta', 'label' => 'Planta/Projeto'],
                ['value' => 'levantamento_topografico', 'label' => 'Levantamento Topográfico'],
                ['value' => 'laudo_ambiental', 'label' => 'Laudo Ambiental'],
                ['value' => 'viabilidade', 'label' => 'Estudo de Viabilidade'],
                ['value' => 'contrato', 'label' => 'Contrato'],
                ['value' => 'procuracao', 'label' => 'Procuração'],
                ['value' => 'rg_cpf', 'label' => 'RG/CPF'],
                ['value' => 'comprovante_residencia', 'label' => 'Comprovante de Residência'],
                ['value' => 'outros', 'label' => 'Outros'],
            ],
        ]);
    }

    /**
     * Listar as categorias de documentos disponíveis.
     */
    public function categorias(): JsonResponse
    {
        Gate::authorize('viewAny', Documento::class);

        return response()->json([
            'data' => [
                ['value' => 'juridico', 'label' => 'Jurídico'],
                ['value' => 'tecnico', 'label' => 'Técnico'],
                ['value' => 'financeiro', 'label' => 'Financeiro'],
                ['value' => 'ambiental', 'label' => 'Ambiental'],
                ['value' => 'pessoal', 'label' => 'Pessoal'],
            ],
        ]);
    }
}
