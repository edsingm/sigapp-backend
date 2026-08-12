<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\HiperdadosImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommitHiperdadosImportRequest;
use App\Http\Requests\Admin\PreviewHiperdadosImportRequest;
use App\Http\Requests\Admin\StartHiperdadosImportRequest;
use App\Http\Resources\Admin\HiperdadosImportResource;
use App\Models\Central\HiperdadosImport;
use App\Models\User;
use App\Services\Admin\HiperdadosImportService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HiperdadosImportController extends Controller
{
    public function __construct(
        private readonly HiperdadosImportService $imports,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', '20')));
        $paginator = $this->imports
            ->paginate($perPage)
            ->through(fn (HiperdadosImport $import): array => HiperdadosImportResource::make($import)->resolve());

        return ApiResponseService::paginated($paginator, 'HIPERDADOS_IMPORTS_LISTED');
    }

    public function store(StartHiperdadosImportRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $result = $this->imports->start(
            admin: $user,
            username: (string) $validated['username'],
            password: (string) $validated['password'],
            limit: isset($validated['limit']) ? (int) $validated['limit'] : null,
        );

        $import = $result['import'];

        $this->audit('hiperdados.import.started', 'Importação Hiperdados iniciada.', [
            'import_id' => $import->uuid,
            'portal_username' => $import->portal_username,
            'limit' => $import->limit_count,
        ]);

        return ApiResponseService::created(
            HiperdadosImportResource::make($import)->resolve(),
            'HIPERDADOS_IMPORT_QUEUED'
        );
    }

    public function show(HiperdadosImport $hiperdadosImport): JsonResponse
    {
        $hiperdadosImport->loadMissing(['creator:id,name,email', 'tenant:id,name,slug']);

        return ApiResponseService::success(
            HiperdadosImportResource::make($hiperdadosImport)->resolve(),
            'HIPERDADOS_IMPORT_RETRIEVED'
        );
    }

    public function preview(
        PreviewHiperdadosImportRequest $request,
        HiperdadosImport $hiperdadosImport,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $preview = $this->imports->preview(
                $hiperdadosImport,
                offset: (int) ($validated['offset'] ?? 0),
                limit: (int) ($validated['limit'] ?? 50),
            );
        } catch (HiperdadosImportException $e) {
            return ApiResponseService::error($e->errorCode, $e->getMessage(), null, $e->statusCode());
        }

        return ApiResponseService::success($preview, 'HIPERDADOS_IMPORT_PREVIEW');
    }

    public function commit(
        CommitHiperdadosImportRequest $request,
        HiperdadosImport $hiperdadosImport,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        try {
            $result = $this->imports->commit(
                $hiperdadosImport,
                (string) $validated['tenant_id'],
                $user,
            );
        } catch (HiperdadosImportException $e) {
            return ApiResponseService::error($e->errorCode, $e->getMessage(), null, $e->statusCode());
        }

        $import = $result['import'];

        $this->audit('hiperdados.import.commit', 'Commit de importação Hiperdados solicitado.', [
            'import_id' => $import->uuid,
            'tenant_id' => $import->tenant_id,
            'total_count' => $import->total_count,
        ]);

        return ApiResponseService::success(
            HiperdadosImportResource::make($import)->resolve(),
            'HIPERDADOS_IMPORT_COMMIT_QUEUED'
        );
    }
}
