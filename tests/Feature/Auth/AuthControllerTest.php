<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ], $attrs));
    }

    private function createAdminUser(): User
    {
        return $this->createUser(['is_admin' => true]);
    }

    // -----------------------------------------------------------------
    // POST /api/v1/auth/login — validação
    // -----------------------------------------------------------------

    public function test_login_rejeita_email_vazio(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_rejeita_senha_vazia(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_rejeita_email_invalido(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    // -----------------------------------------------------------------
    // POST /api/v1/auth/password/forgot — validação
    // -----------------------------------------------------------------

    public function test_forgot_password_rejeita_email_vazio(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_forgot_password_rejeita_email_invalido(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
    }

    // -----------------------------------------------------------------
    // POST /api/v1/auth/password/reset — validação
    // -----------------------------------------------------------------

    public function test_reset_password_rejeita_campos_obrigatorios(): void
    {
        $response = $this->postJson('/api/v1/auth/password/reset', []);

        $response->assertStatus(422);
    }

    public function test_reset_password_rejeita_senha_fraca(): void
    {
        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => 'any-token',
            'password' => '123', // Fraca
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(422);
    }

    // -----------------------------------------------------------------
    // Rotas autenticadas — sem autenticação
    // -----------------------------------------------------------------

    public function test_logout_sem_autenticacao_retorna_401(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }

    public function test_me_sem_autenticacao_retorna_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_refresh_sem_autenticacao_retorna_401(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh');

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------
    // Rotas autenticadas — com autenticação
    // -----------------------------------------------------------------

    public function test_me_retorna_dados_do_usuario(): void
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('test-token', ['admin'])->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email'],
            ]);
    }

    public function test_me_com_user_normal_retorna_403(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test-token', ['tenant-api'])->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(403);
    }

    public function test_logout_revoga_token_atual(): void
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('test-token', ['admin'])->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();
    }

    public function test_logout_all_revoga_todos_os_tokens(): void
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('token-1', ['admin'])->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout-all');

        $response->assertOk();
    }

    public function test_select_tenant_rejeita_campos_obrigatorios(): void
    {
        $response = $this->postJson('/api/v1/auth/select-tenant', []);

        $response->assertStatus(422);
    }

    public function test_select_tenant_rejeita_session_id_vazio(): void
    {
        $response = $this->postJson('/api/v1/auth/select-tenant', [
            'broker_session_id' => '',
            'tenant_id' => '123',
        ]);

        $response->assertStatus(422);
    }
}
