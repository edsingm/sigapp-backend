<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\User;
use Database\Factories\Tenant\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class CorretoresExternosCacheTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            InitializeTenancyFlexible::class,
            AddTenantContextToLogs::class,
            ApiRequestLogger::class,
            CheckSubscriptionStatus::class,
            EnsureTenantContext::class,
            EnsureTenantUser::class,
            CheckFeature::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
        $this->user = UserFactory::new()->createOne();
        Gate::before(static fn (): bool => true);
    }

    public function test_paginated_cache_key_distinguishes_pages(): void
    {
        foreach (range(1, 11) as $index) {
            CorretorExterno::query()->create([
                'nome' => sprintf('Corretor %02d', $index),
                'email' => "corretor{$index}@example.test",
                'telefone' => '11999999999',
                'creci' => 1000 + $index,
            ]);
        }

        $first = $this->actingAs($this->user)->getJson('/api/v1/corretores-externos?per_page=10&page=1');
        $second = $this->actingAs($this->user)->getJson('/api/v1/corretores-externos?per_page=10&page=2');

        $first->assertOk()->assertJsonPath('meta.current_page', 1);
        $second->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(1, 'data');

        $this->assertNotSame($first->json('data.0.id'), $second->json('data.0.id'));
    }

    public function test_create_encrypts_pii_and_list_returns_plaintext(): void
    {
        $email = str_repeat('a', 64).'@example.test';
        $response = $this->actingAs($this->user)->postJson('/api/v1/corretores-externos', [
            'nome' => 'Corretor Seguro',
            'email' => $email,
            'telefone' => '11987654321',
            'creci' => 12345,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', $email)
            ->assertJsonPath('data.telefone', '11987654321');

        $row = DB::table('corretores_externos')->first();
        $this->assertIsObject($row);
        $this->assertStringStartsWith('tenant:v1:', (string) $row->email);
        $this->assertGreaterThan(255, strlen((string) $row->email));
        $this->assertSame(64, strlen((string) $row->email_hash));
        $this->assertSame(64, strlen((string) $row->telefone_hash));

        $this->actingAs($this->user)
            ->getJson('/api/v1/corretores-externos')
            ->assertOk()
            ->assertJsonPath('data.0.email', $email);
    }

    public function test_email_and_phone_search_use_blind_indexes_and_email_remains_unique(): void
    {
        CorretorExterno::query()->create([
            'nome' => 'Corretor Encontrado',
            'email' => 'busca@example.test',
            'telefone' => '(11) 98765-4321',
            'creci' => 54321,
        ]);
        $this->actingAs($this->user)
            ->getJson('/api/v1/corretores-externos?search=BUSCA%40EXAMPLE.TEST')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        DB::table('corretores_externos')->update(['email_hash' => null]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/corretores-externos?search=11987654321')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->user)
            ->postJson('/api/v1/corretores-externos', [
                'nome' => 'Duplicado',
                'email' => 'busca@example.test',
                'telefone' => '11911111111',
                'creci' => 67890,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
