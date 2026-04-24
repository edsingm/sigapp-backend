<?php

namespace App\Repositories;

use App\Models\Central\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminPostRepository
{
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return Post::query()
            ->with('author:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function load(Post $post): Post
    {
        return $post->load('author:id,name');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Post
    {
        return Post::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Post $post, array $data): Post
    {
        $post->update($data);

        return $post->refresh()->load('author:id,name');
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }
}
