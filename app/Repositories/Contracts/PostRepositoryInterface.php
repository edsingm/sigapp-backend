<?php

namespace App\Repositories\Contracts;

use App\Models\Central\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PostRepositoryInterface
{
    public function paginatePublished(?string $category, ?string $search, int $perPage = 12): LengthAwarePaginator;

    public function findPublishedBySlug(string $slug): ?Post;

    /**
     * @return Collection<int, Post>
     */
    public function findRelatedPublished(Post $post, int $limit = 3): Collection;

    /**
     * @return Collection<int, string>
     */
    public function listPublishedCategories(): Collection;
}
