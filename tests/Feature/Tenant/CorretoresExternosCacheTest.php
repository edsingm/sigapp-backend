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
}
