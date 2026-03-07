<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\DocumentoResource;
use App\Models\Tenant\Documento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentosController extends Controller
{
    /**
     * Tipos de arquivo permitidos
     */
    private array $allowedMimeTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.google-earth.kml+xml',
        'application/vnd.google-earth.kmz',
        'application/octet-stream', // For DWG files
    ];

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
        'dwg'
    ];

    private function resolveRelativePath(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        if (Storage::disk('public')->exists($trimmed)) {
            return $trimmed;
        }

        $baseUrl = rtrim((string) config('filesystems.disks.public.url', ''), '/');
        if ($baseUrl === '') {
            $baseUrl = '/storage';
        }

        if ($baseUrl !== '' && str_starts_with($trimmed, $baseUrl)) {
            $relative = ltrim(Str::after($trimmed, $baseUrl), '/');
            return $relative !== '' ? rawurldecode($relative) : null;
        }

        $urlPath = parse_url($trimmed, PHP_URL_PATH);
        if (!$urlPath) {
            $withoutHost = preg_replace('#^https?://[^/]+#i', '', $trimmed) ?? '';
            $urlPath = $withoutHost;
        }

        $path = ltrim($urlPath ?? '', '/');
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        } elseif (str_starts_with($path, 'public-')) {
            $segments = explode('/', $path);
            array_shift($segments);
            $path = implode('/', $segments);
        }

        $path = ltrim($path, '/');

        if ($path === '') {
            return null;
        }

        $decoded = rawurldecode($path);
        return $decoded !== '' ? $decoded : null;
    }

    private function buildPublicUrl(string $path): string
    {
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));

        /** @var \Illuminate\Contracts\Filesystem\Cloud $publicDisk */
        $publicDisk = Storage::disk('public');

        return $publicDisk->url($encodedPath);
    }

    /**
     * Listar documentos com filtros
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Documento::class);

        $tenantId = tenant('id') ?? 'central';
        $filters = $request->only(['terreno_id', 'tipo', 'categoria', 'status', 'search', 'sort_by', 'sort_dir', 'per_page', 'page']);
        $cacheKey = "tenant:{$tenantId}:documentos:index:" . md5(json_encode($filters));

        return \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:documentos"])->remember($cacheKey, now()->addMinutes(30), function () use ($request) {
            $query = Documento::with(['terreno:id,nome', 'createdBy:id,name', 'updatedBy:id,name']);

            // Filtro por área
            if ($request->filled('terreno_id')) {
                $query->where('terreno_id', $request->terreno_id);
            }

            // Filtro por tipo
            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            // Filtro por categoria
            if ($request->filled('categoria')) {
                $query->where('categoria', $request->categoria);
            }

            // Filtro por status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Busca por nome
            if ($request->filled('search')) {
                $query->where('nome', 'like', '%' . $request->search . '%');
            }

            // Ordenação
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            // Paginação
            $perPage = $request->get('per_page', 15);
            $documentos = $query->paginate($perPage);

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
     * Criar novo documento com upload
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Documento::class);

        $validated = $request->validate([
            'terreno_id' => 'required|exists:terrenos,id',
            'arquivo' => [
                'required',
                'file',
                'max:3072', // 3MB
            ],
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
                    'outros'
                ])
            ],
            'categoria' => [
                'nullable',
                Rule::in([
                    'juridico',
                    'tecnico',
                    'financeiro',
                    'ambiental',
                    'pessoal'
                ])
            ],
            'descricao' => 'nullable|string|max:1000',
        ]);

        $file = $request->file('arquivo');

        $tenant = tenant();
        $limitService = new \App\Services\LimitEnforcementService($tenant);

        // $file->getSize() returns bytes, we need KB
        $sizeInKb = (int) ceil($file->getSize() / 1024);

        if (!$limitService->canUploadFile($sizeInKb)) {
            return response()->json([
                'message' => 'Limite de armazenamento atingido para o seu plano.',
                'error' => 'LIMIT_EXCEEDED'
            ], 403);
        }

        // Validar extensão
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedExtensions)) {
            return response()->json([
                'message' => 'Tipo de arquivo não permitido.',
                'errors' => ['arquivo' => ['Extensão não permitida: ' . $extension]]
            ], 422);
        }

        // Gerar nome único e seguro para o arquivo
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBaseName = Str::slug($baseName) ?: 'documento';
        $fileName = now()->format('YmdHis') . '_' . Str::lower((string) Str::uuid()) . '_' . $safeBaseName;
        if ($extension !== '') {
            $fileName .= '.' . $extension;
        }

        // Fazer upload para storage/app/public/documentos
        $path = $file->storeAs('documentos', $fileName, 'public');

        // Criar registro no banco
        $documento = Documento::create([
            'terreno_id' => $validated['terreno_id'],
            'nome' => $validated['nome'] ?? $file->getClientOriginalName(),
            'tipo' => $validated['tipo'] ?? 'outros',
            'categoria' => $validated['categoria'] ?? null,
            'descricao' => $validated['descricao'] ?? null,
            'url' => $this->buildPublicUrl($path),
            'tamanho' => $file->getSize(),
            'status' => 'pendente',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Documento enviado com sucesso.',
            'data' => new DocumentoResource($documento->load(['terreno:id,nome', 'createdBy:id,name']))
        ], 201);
    }

    /**
     * Exibir um documento específico
     */
    public function show(int $id): JsonResponse
    {
        $documento = Documento::with(['terreno:id,nome', 'createdBy:id,name', 'updatedBy:id,name'])
            ->findOrFail($id);
        Gate::authorize('view', $documento);

        return response()->json([
            'data' => new DocumentoResource($documento)
        ]);
    }

    /**
     * Atualizar metadados do documento
     */
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
                    'outros'
                ])
            ],
            'categoria' => [
                'sometimes',
                'nullable',
                Rule::in([
                    'juridico',
                    'tecnico',
                    'financeiro',
                    'ambiental',
                    'pessoal'
                ])
            ],
            'descricao' => 'sometimes|nullable|string|max:1000',
            'status' => ['sometimes', Rule::in(['pendente', 'aprovado', 'rejeitado'])],
        ]);

        $validated['updated_by'] = Auth::id();
        $documento->update($validated);

        return response()->json([
            'message' => 'Documento atualizado com sucesso.',
            'data' => new DocumentoResource($documento->fresh(['terreno:id,nome', 'createdBy:id,name', 'updatedBy:id,name']))
        ]);
    }

    /**
     * Excluir documento e arquivo físico
     */
    public function destroy(int $id): JsonResponse
    {
        $documento = Documento::findOrFail($id);
        Gate::authorize('delete', $documento);

        // Extrair o caminho relativo do arquivo
        $relativePath = $this->resolveRelativePath($documento->url);

        // Deletar arquivo físico se existir
        if ($relativePath && Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }

        $documento->delete();

        return response()->json([
            'message' => 'Documento excluído com sucesso.'
        ]);
    }

    /**
     * Download do arquivo
     */
    public function download(int $id)
    {
        $documento = Documento::findOrFail($id);
        Gate::authorize('view', $documento);

        // Extrair o caminho relativo do arquivo
        $relativePath = $this->resolveRelativePath($documento->url);

        if (!$relativePath) {
            return response()->json([
                'message' => 'Arquivo não encontrado.'
            ], 404);
        }

        if (!Storage::disk('public')->exists($relativePath)) {
            return response()->json([
                'message' => 'Arquivo não encontrado.'
            ], 404);
        }

        $downloadName = $documento->nome;
        $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
        if ($extension !== '' && pathinfo($downloadName, PATHINFO_EXTENSION) === '') {
            $downloadName .= '.' . $extension;
        }

        $absolutePath = Storage::disk('public')->path($relativePath);

        return response()->download($absolutePath, $downloadName);
    }

    /**
     * Visualização do arquivo (inline)
     */
    public function view(int $id)
    {
        $documento = Documento::findOrFail($id);
        Gate::authorize('view', $documento);

        $relativePath = $this->resolveRelativePath($documento->url);

        if (!$relativePath) {
            return response()->json([
                'message' => 'Arquivo não encontrado.'
            ], 404);
        }

        if (!Storage::disk('public')->exists($relativePath)) {
            return response()->json([
                'message' => 'Arquivo não encontrado.'
            ], 404);
        }

        $filename = $documento->nome;
        $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
        if ($extension !== '' && pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.' . $extension;
        }

        $absolutePath = Storage::disk('public')->path($relativePath);

        return response()->file($absolutePath, [
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
        ]);
    }

    /**
     * Listar opções de tipos de documento
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
            ]
        ]);
    }

    /**
     * Listar opções de categorias
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
            ]
        ]);
    }
}
