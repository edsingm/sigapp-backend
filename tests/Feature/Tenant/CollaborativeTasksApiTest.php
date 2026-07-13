<?php

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Http\Middleware\PermissionGate;
use App\Models\Tenant\EntityActivity;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CollaborativeTasksApiTest extends TestCase
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
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Collaboration Admin',
            'email' => 'collaboration-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);

        $this->terreno = Terreno::create([
            'nome' => 'Terreno Collaboration',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_create_list_and_comment_on_task(): void
    {
        $create = $this->actingAs($this->admin)->postJson('/api/v1/tasks', [
            'terreno_id' => $this->terreno->id,
            'related_type' => 'terreno',
            'related_id' => $this->terreno->id,
            'title' => 'Revisar documentação',
            'priority' => 'high',
            'tags' => ['documentos'],
            'due_at' => now()->addDay()->toDateString(),
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.title', 'Revisar documentação')
            ->assertJsonPath('data.tags.0', 'documentos');

        $taskId = $create->json('data.id');

        $this->actingAs($this->admin)
            ->getJson('/api/v1/tasks?overdue=0')
            ->assertOk()
            ->assertJsonPath('data.0.id', $taskId);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/tasks/{$taskId}/comments", ['body' => 'Verificar matrícula.'])
            ->assertCreated()
            ->assertJsonPath('data.comment', 'Verificar matrícula.');

        $this->actingAs($this->admin)
            ->getJson("/api/v1/tasks/{$taskId}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.comment', 'Verificar matrícula.');
    }

    public function test_activity_endpoint_returns_entity_activity_contract(): void
    {
        $activity = EntityActivity::create([
            'terreno_id' => $this->terreno->id,
            'entity_type' => 'terreno',
            'entity_id' => $this->terreno->id,
            'action' => 'created',
            'user_id' => $this->admin->id,
            'summary' => 'Terreno criado',
            'payload_json' => ['source' => 'test'],
            'happened_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/activities?entity_type=terreno&entity_id='.$this->terreno->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $activity->id)
            ->assertJsonPath('data.0.entity.type', 'terreno')
            ->assertJsonPath('data.0.actor.id', $this->admin->id);
    }
}
