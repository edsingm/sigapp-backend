<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CentralUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_central_users(): void
    {
        $this->actingAsCentralAdmin();

        foreach (range(1, 3) as $index) {
            $this->makeUser([
                'name' => "Usuario {$index}",
                'email' => "usuario{$index}@example.com",
            ]);
        }

        $response = $this->adminJson('get', '/api/v1/admin/users');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure([
                'success',
                'data',
                'meta',
            ]);

        $this->assertArrayNotHasKey('password', $response->json('data.0'));
    }

    public function test_non_admin_cannot_access_central_users_api(): void
    {
        $user = $this->makeUser(['is_admin' => false]);
        Sanctum::actingAs($user, ['admin']);

        $response = $this->adminJson('get', '/api/v1/admin/users');

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_central_user(): void
    {
        $this->actingAsCentralAdmin();

        $response = $this->adminJson('post', '/api/v1/admin/users', [
            'name' => 'Novo Admin',
            'email' => 'novo-admin@example.com',
            'password' => 'password123',
            'is_admin' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Novo Admin')
            ->assertJsonPath('data.email', 'novo-admin@example.com')
            ->assertJsonPath('data.is_admin', true);

        $this->assertDatabaseHas('users', [
            'email' => 'novo-admin@example.com',
            'is_admin' => true,
        ]);
    }

    public function test_create_central_user_returns_validation_payload(): void
    {
        $this->actingAsCentralAdmin();

        $response = $this->adminJson('post', '/api/v1/admin/users', [
            'email' => 'invalido',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'message',
                'errors' => ['name', 'email', 'password'],
            ]);
    }

    public function test_admin_can_show_and_update_a_central_user(): void
    {
        $this->actingAsCentralAdmin();
        $managedUser = $this->makeUser([
            'name' => 'Gestao Antiga',
            'email' => 'gestao-antiga@example.com',
            'is_admin' => false,
        ]);

        $this->adminJson('get', "/api/v1/admin/users/{$managedUser->id}")
            ->assertOk()
            ->assertJsonPath('data.email', 'gestao-antiga@example.com');

        $response = $this->adminJson('put', "/api/v1/admin/users/{$managedUser->id}", [
            'name' => 'Gestao Nova',
            'is_admin' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Gestao Nova')
            ->assertJsonPath('data.is_admin', true);

        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'name' => 'Gestao Nova',
            'is_admin' => true,
        ]);
    }

    public function test_admin_cannot_delete_itself(): void
    {
        $admin = $this->actingAsCentralAdmin();

        $response = $this->adminJson('delete', "/api/v1/admin/users/{$admin->id}");

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SELF_DELETION');
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
}
