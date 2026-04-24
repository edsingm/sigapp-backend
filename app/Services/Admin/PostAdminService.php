<?php

namespace App\Services\Admin;

use App\Models\Central\Post;
use App\Repositories\AdminPostRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PostAdminService
{
    public function __construct(
        private readonly AdminPostRepository $repository
    ) {}

    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function show(Post $post): Post
    {
        return $this->repository->load($post);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $authorId): Post
    {
        $payload = [
            ...$data,
            'author_id' => $authorId,
            'slug' => Str::slug((string) $data['title']),
        ];

        return $this->repository->load($this->repository->create($payload));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Post $post, array $data): Post
    {
        if (array_key_exists('title', $data) && is_string($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        return $this->repository->update($post, $data);
    }

    public function delete(Post $post): void
    {
        $this->repository->delete($post);
    }
}
