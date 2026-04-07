<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostController extends Controller
{
    /**
     * Lista todos os artigos com paginação.
     */
    public function index()
    {
        $posts = Post::with('author:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $posts,
        ]);
    }

    /**
     * Cria um novo artigo.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string',
            'content' => 'required|string',
            'category' => 'nullable|string',
            'image' => 'nullable|string',
            'read_time' => 'nullable|string',
            'featured' => 'boolean',
            'published' => 'boolean',
        ]);

        $validated['author_id'] = $request->user()->id;
        $validated['slug'] = Str::slug($validated['title']);

        $post = Post::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Artigo criado com sucesso',
            'data' => $post,
        ], 201);
    }

    /**
     * Exibe os detalhes de um artigo específico.
     */
    public function show(Post $post)
    {
        return response()->json([
            'success' => true,
            'data' => $post->load('author:id,name'),
        ]);
    }

    /**
     * Atualiza um artigo existente.
     */
    public function update(Request $request, Post $post)
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'excerpt' => 'nullable|string',
            'content' => 'string',
            'category' => 'nullable|string',
            'image' => 'nullable|string',
            'read_time' => 'nullable|string',
            'featured' => 'boolean',
            'published' => 'boolean',
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $post->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Artigo atualizado com sucesso',
            'data' => $post,
        ]);
    }

    /**
     * Exclui um artigo.
     */
    public function destroy(Post $post)
    {
        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Artigo excluído com sucesso',
        ]);
    }
}
