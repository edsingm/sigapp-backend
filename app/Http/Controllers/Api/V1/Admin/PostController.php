<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminPostRequest;
use App\Http\Requests\Admin\UpdateAdminPostRequest;
use App\Http\Resources\AdminPostResource;
use App\Models\Central\Post;
use App\Services\Admin\PostAdminService;
use App\Services\ApiResponseService;

class PostController extends Controller
{
    public function __construct(
        private readonly PostAdminService $service
    ) {}

    /**
     * Lista todos os artigos com paginação.
     */
    public function index()
    {
        $posts = $this->service
            ->paginate(10)
            ->through(fn (Post $post): array => AdminPostResource::make($post)->resolve());

        return ApiResponseService::paginated($posts);
    }

    /**
     * Cria um novo artigo.
     */
    public function store(StoreAdminPostRequest $request)
    {
        $post = $this->service->create($request->validated(), $request->user()->id);

        return ApiResponseService::created(
            AdminPostResource::make($post)->resolve(),
            'Artigo criado com sucesso'
        );
    }

    /**
     * Exibe os detalhes de um artigo específico.
     */
    public function show(Post $post)
    {
        return ApiResponseService::success(
            AdminPostResource::make($this->service->show($post))->resolve()
        );
    }

    /**
     * Atualiza um artigo existente.
     */
    public function update(UpdateAdminPostRequest $request, Post $post)
    {
        $post = $this->service->update($post, $request->validated());

        return ApiResponseService::success(
            AdminPostResource::make($post)->resolve(),
            'Artigo atualizado com sucesso'
        );
    }

    /**
     * Exclui um artigo.
     */
    public function destroy(Post $post)
    {
        $this->service->delete($post);

        return ApiResponseService::success(null, 'Artigo excluído com sucesso');
    }
}
