<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostDetailResource;
use App\Http\Resources\BlogPostSummaryResource;
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

        return BlogPostSummaryResource::collection($posts)->additional([
            'success' => true,
        ])->response();
    }

    /**
     * Exibir os detalhes de um artigo específico do blog pelo slug.
     */
    public function show(string $slug): Response
    {
        $payload = $this->blogService->showPublished($slug);

        return response()->json([
            'success' => true,
            'data' => [
                'post' => BlogPostDetailResource::make($payload['post'])->resolve($request = request()),
                'related' => BlogPostSummaryResource::collection($payload['related'])->resolve($request),
            ],
        ]);
    }

    /**
     * Obter a lista de categorias únicas dos artigos publicados.
     */
    public function categories(): Response
    {
        return response()->json([
            'success' => true,
            'data' => $this->blogService->categories()->values()->all(),
        ]);
    }
}
