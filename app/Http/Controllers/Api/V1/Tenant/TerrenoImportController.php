<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Enums\TerrenoImportRowStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ConfirmTerrenoImportRequest;
use App\Http\Requests\Tenant\ListTerrenoImportRowsRequest;
use App\Http\Requests\Tenant\StoreTerrenoImportRequest;
use App\Http\Resources\Tenant\TerrenoImportResource;
use App\Http\Resources\Tenant\TerrenoImportRowResource;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Services\ApiResponseService;
use App\Services\Tenant\TerrenoImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TerrenoImportController extends Controller
{
    public function __construct(private readonly TerrenoImportService $imports) {}

    public function template(): BinaryFileResponse
    {
        Gate::authorize('create', Terreno::class);

        return response()
            ->download($this->imports->templatePath(), 'modelo-importacao-terrenos.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function store(StoreTerrenoImportRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $file = $request->file('arquivo');
        if (! $file instanceof UploadedFile) {
            throw new LogicException('A planilha validada não foi encontrada.');
        }
        $import = $this->imports->create(
            $user,
            (string) $request->validated('idempotency_key'),
            $file,
        );

        return ApiResponseService::success(new TerrenoImportResource($import), 'TERRAIN_IMPORT_QUEUED', 202);
    }

    public function show(Request $request, int $import): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponseService::success(new TerrenoImportResource($this->imports->find($user, $import)));
    }

    public function rows(ListTerrenoImportRowsRequest $request, int $import): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $statusValue = $request->validated('status');
        $status = is_string($statusValue) ? TerrenoImportRowStatus::from($statusValue) : null;
        $paginator = $this->imports
            ->rows($user, $import, $status, (int) $request->validated('per_page', 50))
            ->through(fn ($row): array => TerrenoImportRowResource::make($row)->resolve());

        return ApiResponseService::paginated($paginator);
    }

    public function confirm(ConfirmTerrenoImportRequest $request, int $import): JsonResponse
    {
        Gate::authorize('create', Terreno::class);
        /** @var User $user */
        $user = $request->user();

        return ApiResponseService::success(
            new TerrenoImportResource($this->imports->confirm($user, $import)),
            'TERRAIN_IMPORT_CONFIRMED',
            202,
        );
    }

    public function errors(Request $request, int $import): BinaryFileResponse
    {
        Gate::authorize('viewAny', Terreno::class);
        /** @var User $user */
        $user = $request->user();

        return response()
            ->download($this->imports->errorReportPath($user, $import), "importacao-terrenos-{$import}-erros.xlsx")
            ->deleteFileAfterSend(true);
    }
}
