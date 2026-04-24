<?php

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Central\Cidade;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CidadesApiTest extends TestCase
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
            CheckFeature::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        $this->user = User::create([
            'name' => 'Cidades User',
            'email' => 'cidades-user@test.com',
            'password' => Hash::make('password123'),
        ]);

        Cidade::create([
            'code' => '3550308',
            'city' => 'São Paulo',
            'state' => 'São Paulo',
            'state_code' => 'SP',
            'latitude' => -23.55052,
            'longitude' => -46.633308,
        ]);

        Cidade::create([
            'code' => '3304557',
            'city' => 'Rio de Janeiro',
            'state' => 'Rio de Janeiro',
            'state_code' => 'RJ',
            'latitude' => -22.906847,
            'longitude' => -43.172897,
        ]);
    }

    public function test_authenticated_user_can_list_states_and_cities_by_state(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/cidades/estados')
            ->assertOk()
            ->assertJsonPath('data.0.state_code', 'RJ');

        $this->actingAs($this->user)
            ->getJson('/api/v1/cidades/SP')
            ->assertOk()
            ->assertJsonPath('data.0.code', '3550308')
            ->assertJsonPath('data.0.name', 'São Paulo');
    }

    public function test_authenticated_user_can_search_city_and_fetch_details(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/cidades/buscar?termo=Paulo')
            ->assertOk()
            ->assertJsonPath('status', 'OK')
            ->assertJsonPath('data.0.code', '3550308');

        $this->actingAs($this->user)
            ->getJson('/api/v1/cidades/dados?cityCode=3550308')
            ->assertOk()
            ->assertJsonPath('status', 'OK')
            ->assertJsonPath('data.city', 'São Paulo');
    }

    public function test_city_search_and_details_validate_query_parameters(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/cidades/buscar?termo=S')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['termo']);

        $this->actingAs($this->user)
            ->getJson('/api/v1/cidades/dados')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cityCode']);
    }
}
