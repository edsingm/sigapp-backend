<?php

namespace App\Services\Tenant;

use App\Http\Requests\Tenant\FilterTerrenosRequest;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoInfos;
use App\Models\Tenant\User;
use App\Repositories\Tenant\TerrenoRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

class TerrenoService
{
    public function __construct(
        private readonly TerrenoRepository $repository,
        private readonly LandWorkflowService $workflowService,
        private readonly KmzParserService $kmzParser,
        private readonly TerrenoFilterService $filterService,
    ) {}

    public function listPaginated(FilterTerrenosRequest|\Illuminate\Http\Request $request): LengthAwarePaginator
    {
        $tenantId = tenant('id') ?? 'central';
        $forceRefresh = $request->boolean('force_refresh', false);
        $filters = [
            'search' => $request->input('search'),
            'per_page' => (int) $request->input('per_page', 15),
            'page' => (int) $request->input('page', 1),
        ];

        $cacheKey = "tenant:{$tenantId}:terrenos:index:".md5(json_encode($filters));
        $cacheStore = Cache::tags(["tenant:{$tenantId}:terrenos"]);
        $resolver = fn (): LengthAwarePaginator => $this->repository->paginate($filters);

        if ($forceRefresh) {
            $cacheStore->forget($cacheKey);
            $paginator = $resolver();
            $cacheStore->put($cacheKey, $paginator, now()->addMinutes(30));

            return $paginator;
        }

        return $cacheStore->remember($cacheKey, now()->addMinutes(30), $resolver);
    }

    public function filter(FilterTerrenosRequest $request): LengthAwarePaginator
    {
        $tenantId = tenant('id') ?? 'central';
        $forceRefresh = $request->boolean('force_refresh', false);
        $filters = $request->except(['force_refresh']);
        $cacheKey = "tenant:{$tenantId}:terrenos:filter:".md5(json_encode($filters));
        $cacheStore = Cache::tags(["tenant:{$tenantId}:terrenos"]);
        $resolver = fn (): LengthAwarePaginator => $this->filterService->filter($request);

        if ($forceRefresh) {
            $cacheStore->forget($cacheKey);
            $paginator = $resolver();
            $cacheStore->put($cacheKey, $paginator, now()->addMinutes(30));

            return $paginator;
        }

        return $cacheStore->remember($cacheKey, now()->addMinutes(30), $resolver);
    }

    public function create(array $data, User $actor): Terreno
    {
        unset($data['workflow_status_code']);
        $data['created_by'] = $actor->id;

        $terreno = $this->repository->create($data);
        $this->workflowService->initialize($terreno, $actor);

        return $this->repository->loadDetailRelations($terreno);
    }

    public function show(Terreno $terreno): Terreno
    {
        return $this->repository->loadDetailRelations($terreno);
    }

    public function findOrFail(int|string $id): Terreno
    {
        return $this->repository->findOrFail($id);
    }

    public function findInfoOrFail(int|string $id): TerrenoInfos
    {
        return $this->repository->findInfoOrFail($id);
    }

    public function update(Terreno $terreno, array $data, User $actor): Terreno
    {
        $data['updated_by'] = $actor->id;

        $terreno = $this->repository->update($terreno, $data);
        $this->workflowService->syncReadiness($terreno, $actor, 'terrain_updated');

        return $this->repository->loadDetailRelations($terreno);
    }

    public function delete(Terreno $terreno): void
    {
        $this->repository->delete($terreno);
    }

    /**
     * @return Collection<int, Terreno>
     */
    public function listForSelect(): Collection
    {
        return $this->repository->listForSelect();
    }

    public function storeInfo(Terreno $terreno, string $descricao, User $actor): TerrenoInfos
    {
        $info = $this->repository->createInfo($terreno, [
            'descricao' => $descricao,
            'created_by' => $actor->id,
            'user_id' => $actor->id,
        ]);

        return $info->load('user');
    }

    /**
     * @return Collection<int, TerrenoInfos>
     */
    public function listInfos(Terreno $terreno): Collection
    {
        return $this->repository->listInfos($terreno);
    }

    public function updateInfo(TerrenoInfos $info, string $descricao): TerrenoInfos
    {
        return $this->repository
            ->updateInfo($info, ['descricao' => $descricao])
            ->load('createdBy');
    }

    public function deleteInfo(TerrenoInfos $info): void
    {
        $this->repository->deleteInfo($info);
    }

    public function importPolygon(Terreno $terreno, UploadedFile $file, User $actor): Terreno
    {
        $coords = $this->kmzParser->parse($file);

        $terreno = $this->repository->update($terreno, [
            'polygon_coords' => $coords,
            'updated_by' => $actor->id,
        ]);

        return $this->repository->loadDetailRelations($terreno);
    }
}
