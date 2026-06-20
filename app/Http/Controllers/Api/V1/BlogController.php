<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostDetailResource;
use App\Http\Resources\BlogPostSummaryResource;
use App\Services\ApiResponseService;
use App\Services\BlogService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlogController extends Controller
{
    public function __construct(
        private readonly BlogService $blogService,
    ) {}

    /**
     * Listar todos os artigos publicados no blog.
     */
    public function index(Request $request): Response
    {
        $posts = $this->blogService->listPublished(
            $request->string('category')->toString() ?: null,
            $request->string('search')->toString() ?: null,
            min(max($request->integer('per_page', 12), 1), 50),
        );

        return BlogPostSummaryResource::collection($posts)->response();
    }

    /**
     * Exibir os detalhes de um artigo específico do blog pelo slug.
     */
    public function show(string $slug): Response
    {
        $payload = $this->blogService->showPublished($slug);
        $request = request();

        return ApiResponseService::success([
            'post' => BlogPostDetailResource::make($payload['post'])->resolve($request),
            'related' => BlogPostSummaryResource::collection($payload['related'])->resolve($request),
        ]);
    }

    /**
     * Obter a lista de categorias únicas dos artigos publicados.
     */
    public function categories(): Response
    {
        return ApiResponseService::success(
            $this->blogService->categories()->values()->all()
        );
    }
}
