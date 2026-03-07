<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Central\Post;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = Post::with('author:id,name')
            ->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('published_at', 'desc');

        if ($request->has('category') && $request->category !== 'Todos') {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $posts = $query->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $posts
        ]);
    }

    public function show($slug)
    {
        $post = Post::with('author:id,name')
            ->where('slug', $slug)
            ->where('published', true)
            ->firstOrFail();

        // Posts relacionados (mesma categoria, excluindo o atual)
        $related = Post::where('category', $post->category)
            ->where('id', '!=', $post->id)
            ->where('published', true)
            ->limit(3)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'post' => $post,
                'related' => $related
            ]
        ]);
    }

    public function categories()
    {
        $categories = Post::where('published', true)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
}
