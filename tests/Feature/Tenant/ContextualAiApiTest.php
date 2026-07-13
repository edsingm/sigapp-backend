<?php

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\AiBudgetCheck;
use App\Http\Middleware\AiRateLimit;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Http\Middleware\PermissionGate;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContextualAiApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Terreno $terreno;

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
            PermissionGate::class,
            AiRateLimit::class,
            AiBudgetCheck::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Contextual AI Admin',
            'email' => 'contextual-ai-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);
        $this->terreno = Terreno::create(['nome' => 'Terreno IA contextual', 'created_by' => $this->admin->id]);
    }

    public function test_admin_can_generate_and_apply_a_contextual_task_recommendation(): void
    {
        $context = $this->actingAs($this->admin)->postJson('/api/v1/ai/context', [
            'entity_type' => 'terreno',
            'entity_id' => $this->terreno->id,
            'intent' => 'workflow',
            'action' => 'create_task',
            'parameters' => ['task_title' => 'Revisar checklist do terreno'],
        ]);
        $recommendation = $context->assertOk()->assertJsonPath('data.status', 'proposed')->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/v1/ai/recommendations/{$recommendation}/apply", [
            'confirmation' => true,
            'justification' => 'Ação confirmada pelo responsável.',
        ])->assertOk()->assertJsonPath('data.status', 'applied');

        $this->assertDatabaseHas('tasks', [
            'terreno_id' => $this->terreno->id,
            'title' => 'Revisar checklist do terreno',
        ]);
    }
}
