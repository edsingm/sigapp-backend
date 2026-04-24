<?php

namespace Tests\Feature\Admin;

use App\Models\Central\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CentralPostApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_posts_with_resources(): void
    {
        $admin = $this->actingAsCentralAdmin();

        $createResponse = $this->adminJson('post', '/api/v1/admin/posts', [
            'title' => 'Post Admin',
            'excerpt' => 'Resumo admin',
            'content' => 'Conteudo admin',
            'category' => 'Noticias',
            'featured' => true,
            'published' => true,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Post Admin')
            ->assertJsonPath('data.slug', 'post-admin')
            ->assertJsonPath('data.author.id', $admin->id);

        $postId = $createResponse->json('data.id');

        $this->adminJson('get', '/api/v1/admin/posts')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'meta',
            ]);

        $this->adminJson('get', "/api/v1/admin/posts/{$postId}")
            ->assertOk()
            ->assertJsonPath('data.content', 'Conteudo admin');

        $this->adminJson('put', "/api/v1/admin/posts/{$postId}", [
            'title' => 'Post Atualizado',
            'published' => false,
        ])->assertOk()
            ->assertJsonPath('data.title', 'Post Atualizado')
            ->assertJsonPath('data.slug', 'post-atualizado')
            ->assertJsonPath('data.published', false);

        $this->adminJson('delete', "/api/v1/admin/posts/{$postId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeletedOrMissing($postId);
    }

    public function test_post_endpoints_validate_and_protect_non_admins(): void
    {
        $this->actingAsCentralAdmin();

        $this->adminJson('post', '/api/v1/admin/posts', [
            'title' => '',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $user = $this->makeUser(['is_admin' => false]);
        Sanctum::actingAs($user, ['admin']);

        $this->adminJson('get', '/api/v1/admin/posts')->assertForbidden();
    }

    private function actingAsCentralAdmin(): User
    {
        $user = $this->makeUser(['is_admin' => true]);

        Sanctum::actingAs($user, ['admin']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUser(array $attributes = []): User
    {
        return User::create([
            'name' => $attributes['name'] ?? 'Admin Central',
            'email' => $attributes['email'] ?? ('user-'.uniqid().'@example.com'),
            'password' => $attributes['password'] ?? Hash::make('password123'),
            'is_admin' => $attributes['is_admin'] ?? true,
        ]);
    }

    private function adminJson(string $method, string $uri, array $payload = [])
    {
        return $this
            ->withHeader('Host', 'localhost')
            ->{$method.'Json'}($uri, $payload);
    }

    private function assertSoftDeletedOrMissing(int $postId): void
    {
        $this->assertDatabaseMissing('posts', ['id' => $postId]);
    }
}
