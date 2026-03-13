<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\DocumentoResource;
use App\Models\Tenant\Documento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentosController extends Controller
{
    private const STORAGE_DISK = 'local';

    private array $allowedExtensions = [
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

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Documento::class);

        $tenantId = tenant('id') ?? 'central';
        $filters = $request->only(['terreno_id', 'tipo', 'categoria', 'status', 'search', 'sort_by', 'sort_dir', 'per_page', 'page']);
        $cacheKey = "tenant:{$tenantId}:documentos:index:" . md5(json_encode($filters));

        return \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:documentos"])->remember($cacheKey, now()->addMinutes(30), function () use ($request) {
            $query = Documento::with(['terreno:id,nome', 'createdBy:id,name', 'updatedBy:id,name']);

            if ($request->filled('terreno_id')) {
                $query->where('terreno_id', $request->terreno_id);
            }

            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            if ($request->filled('categoria')) {
                $query->where('categoria', $request->categoria);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $query->where('nome', 'like', '%' . $request->search . '%');
            }

            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');
            $perPage = $request->get('per_page', 15);
            $documentos = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

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

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Documento::class);

        $validated = $request->validate([
            'terreno_id' => 'required|exists:terrenos,id',
            'arquivo' => ['required', 'file', 'max:3072'],
            'nome' => 'nullable|string|max:255',
            'tipo' => [
                'nullable',
                Rule::in([
                    'escritura',
                    'matricula',
                    'certidao_negativa',
                    'iptu',
                    'planta',
                    'levantamento_topografico',
                    'laudo_ambiental',
                    'viabilidade',
                    'contrato',
                    'procuracao',
                    'rg_cpf',
                    'comprovante_residencia',
                    'outros',
                ]),
            ],
            'categoria' => [
                'nullable',
                Rule::in([
                    'juridico',
                    'tecnico',
                    'financeiro',
                    'ambiental',
                    'pessoal',
                ]),
            ],
            'descricao' => 'nullable|string|max:1000',
        ]);

        $file = $request->file('arquivo');
        $tenant = tenant();
        $limitService = new \App\Services\LimitEnforcementService($tenant);
        $sizeInKb = (int) ceil($file->getSize() / 1024);

        if (!$limitService->canUploadFile($sizeInKb)) {
            return response()->json([
                'message' => 'Limite de armazenamento atingido para o seu plano.',
                'error' => 'LIMIT_EXCEEDED',
            ], 403);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedExtensions, true)) {
            return response()->json([
                'message' => 'Tipo de arquivo não permitido.',
                'errors' => ['arquivo' => ['Extensão não permitida: ' . $extension]],
            ], 422);
        }

        $originalName = $file->getClientOriginalName();
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBaseName = Str::slug($baseName) ?: 'documento';
        $fileName = now()->format('YmdHis') . '_' . Str::lower((string) Str::uuid()) . '_' . $safeBaseName;

        if ($extension !== '') {
            $fileName .= '.' . $extension;
        }

        $path = $file->storeAs('documentos', $fileName, self::STORAGE_DISK);

        $documento = Documento::create([
            'terreno_id' => $validated['terreno_id'],
            'nome' => $validated['nome'] ?? $originalName,
            'tipo' => $validated['tipo'] ?? 'outros',
            'categoria' => $validated['categoria'] ?? null,
            'descricao' => $validated['descricao'] ?? null,
            'file_path' => $path,
            'tamanho' => $file->getSize(),
            'status' => 'pendente',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Documento enviado com sucesso.',
            'data' => new DocumentoResource($documento->load(['terreno:id,nome', 'createdBy:id,name'])),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $documento = Documento::with(['terreno:id,nome', 'createdBy:id,name', 'updatedBy:id,name'])
            ->findOrFail($id);
        Gate::authorize('view', $documento);

        return response()->json([
            'data' => new DocumentoResource($documento),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $documento = Documento::findOrFail($id);
        Gate::authorize('update', $documento);

        $validated = $request->validate([
            'nome' => 'sometimes|string|max:255',
            'tipo' => [
                'sometimes',
                Rule::in([
                    'escritura',
                    'matricula',
                    'certidao_negativa',
                    'iptu',
                    'planta',
                    'levantamento_topografico',
                    'laudo_ambiental',
                    'viabilidade',
                    'contrato',
                    'procuracao',
                    'rg_cpf',
                    'comprovante_residencia',
                    'outros',
                ]),
            ],
            'categoria' => [
                'sometimes',
                'nullable',
                Rule::in([
                    'juridico',
                    'tecnico',
                    'financeiro',
                    'ambiental',
                    'pessoal',
                ]),
            ],
            'descricao' => 'sometimes|nullable|string|max:1000',
            'status' => ['sometimes', Rule::in(['pendente', 'aprovado', 'rejeitado'])],
        ]);

        $validated['updated_by'] = Auth::id();
        $documento->update($validated);

        return response()->json([
            'message' => 'Documento atualizado com sucesso.',
            'data' => new DocumentoResource($documento->fresh(['terreno:id,nome', 'createdBy:id,name', 'updatedBy:id,name'])),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $documento = Documento::findOrFail($id);
        Gate::authorize('delete', $documento);

        if ($documento->file_path && Storage::disk(self::STORAGE_DISK)->exists($documento->file_path)) {
            Storage::disk(self::STORAGE_DISK)->delete($documento->file_path);
        }

        $documento->delete();

        return response()->json([
            'message' => 'Documento excluído com sucesso.',
        ]);
    }

    public function download(int $id)
    {
        $documento = Documento::findOrFail($id);
        Gate::authorize('view', $documento);

        if (!$documento->file_path || !Storage::disk(self::STORAGE_DISK)->exists($documento->file_path)) {
            return response()->json([
                'message' => 'Arquivo não encontrado.',
            ], 404);
        }

        $downloadName = $documento->nome;
        $extension = pathinfo($documento->file_path, PATHINFO_EXTENSION);

        if ($extension !== '' && pathinfo($downloadName, PATHINFO_EXTENSION) === '') {
            $downloadName .= '.' . $extension;
        }

        return response()->download(
            Storage::disk(self::STORAGE_DISK)->path($documento->file_path),
            $downloadName
        );
    }

    public function view(int $id)
    {
        $documento = Documento::findOrFail($id);
        Gate::authorize('view', $documento);

        if (!$documento->file_path || !Storage::disk(self::STORAGE_DISK)->exists($documento->file_path)) {
            return response()->json([
                'message' => 'Arquivo não encontrado.',
            ], 404);
        }

        $filename = $documento->nome;
        $extension = pathinfo($documento->file_path, PATHINFO_EXTENSION);

        if ($extension !== '' && pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.' . $extension;
        }

        return response()->file(
            Storage::disk(self::STORAGE_DISK)->path($documento->file_path),
            [
                'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
            ]
        );
    }

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
