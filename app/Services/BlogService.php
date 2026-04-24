<?php

namespace App\Services;

use App\Models\Central\Post;
use App\Repositories\Contracts\PostRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BlogService
{
    public function __construct(
        private readonly PostRepositoryInterface $postRepository,
    ) {}

    public function listPublished(?string $category, ?string $search, int $perPage = 12): LengthAwarePaginator
    {
        return $this->postRepository->paginatePublished($category, $search, $perPage);
    }

    /**
     * @return array{post: Post, related: Collection<int, Post>}
     */
    public function showPublished(string $slug): array
    {
        $post = $this->postRepository->findPublishedBySlug($slug);

        if (! $post) {
            throw new NotFoundHttpException('Post não encontrado.');
        }

        return [
            'post' => $post,
            'related' => $this->postRepository->findRelatedPublished($post),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    public function categories(): Collection
    {
        return $this->postRepository->listPublishedCategories();
    }
}
