<?php

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\AiBudgetCheck;
use App\Http\Middleware\AiRateLimit;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiAutomationApiTest extends TestCase
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
            CheckFeature::class,
            AiRateLimit::class,
            AiBudgetCheck::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'AI Automation Admin',
            'email' => 'ai-automation-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');

        $this->terreno = Terreno::create([
            'nome' => 'Terreno AI Automation',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_create_and_update_ai_task(): void
    {
        $createResponse = $this->actingAs($this->admin)->postJson('/api/v1/ai/automation/tasks', [
            'terreno_id' => $this->terreno->id,
            'title' => 'Revisar matrícula',
            'description' => 'Verificar pendências documentais',
            'priority' => 'high',
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.title', 'Revisar matrícula')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'high');

        $taskId = $createResponse->json('data.id');

        $this->actingAs($this->admin)->putJson("/api/v1/ai/automation/tasks/{$taskId}", [
            'status' => 'in_progress',
            'assigned_to' => $this->admin->id,
        ])->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.assigned_to', $this->admin->id);
    }

    public function test_ai_task_endpoints_validate_payload(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/ai/automation/tasks', [
            'terreno_id' => $this->terreno->id,
            'priority' => 'invalid',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'priority']);

        $task = Task::create([
            'terreno_id' => $this->terreno->id,
            'title' => 'Tarefa existente',
            'status' => 'open',
            'priority' => 'normal',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->putJson("/api/v1/ai/automation/tasks/{$task->id}", [
            'status' => 'invalid',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }
}
