<?php

namespace Tests\Feature\Api;

use App\Models\Central\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BlogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_index_returns_only_published_posts(): void
    {
        $author = $this->makeAuthor();

        Post::create([
            'title' => 'Publicado',
            'slug' => 'publicado',
            'excerpt' => 'Resumo publicado',
            'content' => 'Conteúdo publicado',
            'category' => 'Mercado',
            'published' => true,
            'published_at' => now()->subDay(),
            'author_id' => $author->id,
        ]);

        Post::create([
            'title' => 'Rascunho',
            'slug' => 'rascunho',
            'excerpt' => 'Resumo rascunho',
            'content' => 'Conteúdo rascunho',
            'category' => 'Mercado',
            'published' => false,
            'published_at' => now()->subDay(),
            'author_id' => $author->id,
        ]);

        Post::create([
            'title' => 'Futuro',
            'slug' => 'futuro',
            'excerpt' => 'Resumo futuro',
            'content' => 'Conteúdo futuro',
            'category' => 'Mercado',
            'published' => true,
            'published_at' => now()->addDay(),
            'author_id' => $author->id,
        ]);

        $response = $this
            ->withHeader('Host', 'localhost')
            ->getJson('/api/v1/blog');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'publicado')
            ->assertJsonPath('data.0.author.name', $author->name);

        $response->assertJsonStructure([
            'success',
            'data',
            'links',
            'meta',
        ]);
    }

    public function test_blog_show_returns_post_with_related_posts(): void
    {
        $author = $this->makeAuthor();

        $main = Post::create([
            'title' => 'Post Principal',
            'slug' => 'post-principal',
            'excerpt' => 'Resumo principal',
            'content' => 'Conteúdo principal',
            'category' => 'Produto',
            'published' => true,
            'published_at' => now()->subDay(),
            'author_id' => $author->id,
        ]);

        Post::create([
            'title' => 'Relacionado 1',
            'slug' => 'relacionado-1',
            'excerpt' => 'Resumo relacionado',
            'content' => 'Conteúdo relacionado',
            'category' => 'Produto',
            'published' => true,
            'published_at' => now()->subHours(2),
            'author_id' => $author->id,
        ]);

        Post::create([
            'title' => 'Outro Tema',
            'slug' => 'outro-tema',
            'excerpt' => 'Resumo outro',
            'content' => 'Conteúdo outro',
            'category' => 'Mercado',
            'published' => true,
            'published_at' => now()->subHours(3),
            'author_id' => $author->id,
        ]);

        $response = $this
            ->withHeader('Host', 'localhost')
            ->getJson("/api/v1/blog/{$main->slug}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.post.slug', 'post-principal')
            ->assertJsonPath('data.post.author.name', $author->name)
            ->assertJsonCount(1, 'data.related')
            ->assertJsonPath('data.related.0.slug', 'relacionado-1');
    }

    public function test_blog_categories_returns_unique_published_categories(): void
    {
        $author = $this->makeAuthor();

        Post::create([
            'title' => 'A',
            'slug' => 'a',
            'excerpt' => 'Resumo A',
            'content' => 'Conteúdo A',
            'category' => 'Mercado',
            'published' => true,
            'published_at' => now()->subDay(),
            'author_id' => $author->id,
        ]);

        Post::create([
            'title' => 'B',
            'slug' => 'b',
            'excerpt' => 'Resumo B',
            'content' => 'Conteúdo B',
            'category' => 'Mercado',
            'published' => true,
            'published_at' => now()->subHours(10),
            'author_id' => $author->id,
        ]);

        Post::create([
            'title' => 'C',
            'slug' => 'c',
            'excerpt' => 'Resumo C',
            'content' => 'Conteúdo C',
            'category' => 'Produto',
            'published' => true,
            'published_at' => now()->subHours(6),
            'author_id' => $author->id,
        ]);

        Post::create([
            'title' => 'D',
            'slug' => 'd',
            'excerpt' => 'Resumo D',
            'content' => 'Conteúdo D',
            'category' => 'Oculta',
            'published' => false,
            'published_at' => now()->subHours(5),
            'author_id' => $author->id,
        ]);

        $response = $this
            ->withHeader('Host', 'localhost')
            ->getJson('/api/v1/blog/categories');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(['Mercado', 'Produto'], $response->json('data'));
    }

    private function makeAuthor(): User
    {
        return User::create([
            'name' => 'Autor Teste',
            'email' => 'autor'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);
    }
}
