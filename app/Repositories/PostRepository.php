<?php

namespace App\Repositories;

use App\Models\Central\Post;
use App\Repositories\Contracts\PostRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PostRepository implements PostRepositoryInterface
{
    public function paginatePublished(?string $category, ?string $search, int $perPage = 12): LengthAwarePaginator
    {
        $query = $this->publishedQuery();

        if ($category !== null && $category !== '' && $category !== 'Todos') {
            $query->where('category', $category);
        }

        if ($search !== null && $search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function findPublishedBySlug(string $slug): ?Post
    {
        return $this->publishedQuery()
            ->where('slug', $slug)
            ->first();
    }

    public function findRelatedPublished(Post $post, int $limit = 3): Collection
    {
        return $this->publishedQuery()
            ->where('category', $post->category)
            ->whereKeyNot($post->getKey())
            ->limit($limit)
            ->get();
    }

    public function listPublishedCategories(): Collection
    {
        return Post::query()
            ->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotNull('category')
            ->orderBy('category')
            ->distinct()
            ->pluck('category');
    }

    private function publishedQuery()
    {
        return Post::query()
            ->with('author:id,name')
            ->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');
    }
}
