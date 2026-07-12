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
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommitteeMeetingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ComiteRevisao $review;

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
            'name' => 'Committee Meeting Admin',
            'email' => 'committee-meeting-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);

        $terreno = Terreno::create(['nome' => 'Terreno para reunião', 'created_by' => $this->admin->id]);
        $viabilidade = Viabilidade::create([
            'terreno_id' => $terreno->id,
            'version' => 1,
            'is_current' => true,
            'status' => 'ativo',
            'approval_status' => 'aprovada',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
        $this->review = ComiteRevisao::create([
            'terreno_id' => $terreno->id,
            'viabilidade_id' => $viabilidade->id,
            'status' => 'aguardando_comite',
            'required_departments' => ['comercial'],
        ]);
    }

    public function test_admin_can_run_a_committee_meeting_and_approve_minutes(): void
    {
        $session = $this->actingAs($this->admin)->postJson('/api/v1/comite/sessions', [
            'comite_revisao_id' => $this->review->id,
            'title' => 'Comitê semanal de aprovação',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'meeting_mode' => 'online',
        ])->assertCreated()->assertJsonPath('data.title', 'Comitê semanal de aprovação')->json('data.id');

        $item = $this->actingAs($this->admin)->postJson("/api/v1/comite/sessions/{$session}/agenda-items", [
            'title' => 'Revisar viabilidade',
            'position' => 0,
            'decision_required' => true,
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/v1/comite/sessions/{$session}/participants", [
            'user_id' => $this->admin->id,
            'role' => 'chair',
        ])->assertCreated()->assertJsonPath('data.user_id', $this->admin->id);

        $this->actingAs($this->admin)->postJson("/api/v1/comite/sessions/{$session}/start")
            ->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->actingAs($this->admin)->putJson("/api/v1/comite/sessions/{$session}/minutes", [
            'summary' => 'Discussão concluída.',
            'decisions' => [['agenda_item_id' => $item, 'decision' => 'seguir']],
            'approved' => true,
        ])->assertOk()->assertJsonPath('data.approved_by', $this->admin->id);

        $this->actingAs($this->admin)->postJson("/api/v1/comite/sessions/{$session}/close")
            ->assertOk()->assertJsonPath('data.status', 'closed');

        $this->actingAs($this->admin)->getJson('/api/v1/comite/sessions')
            ->assertOk()->assertJsonPath('data.0.id', $session);
    }
}
