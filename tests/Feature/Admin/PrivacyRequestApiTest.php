<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PrivacyRequestKind;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacySubjectType;
use App\Jobs\GenerateTenantPortabilityJob;
use App\Models\AuditLog;
use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivacyRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_privacy_inbox(): void
    {
        $this->withHeader('Host', 'localhost')
            ->getJson('/api/v1/admin/privacy/requests')
            ->assertUnauthorized();
    }

    public function test_admin_can_create_list_and_update_dsar(): void
    {
        $this->actingAsCentralAdmin();

        $created = $this->withHeader('Host', 'localhost')
            ->postJson('/api/v1/admin/privacy/requests', [
                'kind' => PrivacyRequestKind::PORTABILITY->value,
                'subject_type' => PrivacySubjectType::TENANT_USER->value,
                'subject_email' => 'titular@example.com',
                'notes' => 'Recebido em dpo@sigapp.com.br',
            ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'portability')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.subject_email', 'titular@example.com');

        $this->assertStringStartsWith('LGPD-'.now()->format('Y').'-', (string) $created->json('data.protocol'));

        $id = $created->json('data.id');
        $this->assertIsInt($id);

        $this->withHeader('Host', 'localhost')
            ->getJson('/api/v1/admin/privacy/requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->withHeader('Host', 'localhost')
            ->patchJson('/api/v1/admin/privacy/requests/'.$id, [
                'status' => PrivacyRequestStatus::REFUSED->value,
                'legal_hold_reason' => 'Identidade não confirmada',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'refused');

        $this->assertDatabaseHas('privacy_requests', [
            'id' => $id,
            'status' => 'refused',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'privacy.request_opened']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'privacy.request_updated']);
    }

    public function test_admin_can_flag_dpo_and_list_privileged_access(): void
    {
        $admin = $this->actingAsCentralAdmin();

        $this->withHeader('Host', 'localhost')
            ->putJson('/api/v1/admin/users/'.$admin->id, ['is_dpo' => true])
            ->assertOk()
            ->assertJsonPath('data.is_dpo', true);

        $this->assertTrue((bool) $admin->refresh()->is_dpo);

        $tenant = Tenant::query()->create([
            'name' => 'Privileged',
            'slug' => 'privileged-tenant',
            'status' => Tenant::STATUS_ACTIVE,
            'database_created' => false,
        ]);

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'tenant.privileged_access',
            'description' => 'acesso',
            'metadata' => ['tenant_id' => (string) $tenant->getKey()],
        ]);

        $this->withHeader('Host', 'localhost')
            ->getJson('/api/v1/admin/tenants/'.$tenant->getKey().'/privileged-access')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'tenant.privileged_access');
    }

    public function test_admin_can_offboard_and_wipe_requires_confirmation(): void
    {
        $this->actingAsCentralAdmin();

        $tenant = Tenant::query()->create([
            'name' => 'Offboard Me',
            'slug' => 'offboard-me',
            'status' => Tenant::STATUS_ACTIVE,
            'stripe_id' => 'cus_offboard',
            'database_created' => false,
        ]);
        $tenant->forceFill(['billing_tax_id' => '52998224725'])->save();

        $this->withHeader('Host', 'localhost')
            ->postJson('/api/v1/admin/tenants/'.$tenant->getKey().'/offboard')
            ->assertOk()
            ->assertJsonPath('message', 'Offboarding do tenant agendado');

        $tenant->refresh();
        $this->assertSame(Tenant::STATUS_CANCELLED, $tenant->getAttribute('status'));
        $this->assertNotNull($tenant->getAttribute('wipe_scheduled_at'));

        $this->withHeader('Host', 'localhost')
            ->postJson('/api/v1/admin/tenants/'.$tenant->getKey().'/wipe')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirm');

        $this->withHeader('Host', 'localhost')
            ->postJson('/api/v1/admin/tenants/'.$tenant->getKey().'/wipe', ['confirm' => true])
            ->assertOk();

        $tenant->refresh();
        $this->assertNotNull($tenant->getAttribute('wiped_at'));
        $this->assertSame('cus_offboard', $tenant->getAttribute('stripe_id'));
        $this->assertSame('52998224725', $tenant->getAttribute('billing_tax_id'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'privacy.tenant_offboarded']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'privacy.tenant_wiped']);
    }

    public function test_admin_portability_export_rejects_tenant_without_database(): void
    {
        $this->actingAsCentralAdmin();
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'No Schema',
            'slug' => 'no-schema',
            'status' => Tenant::STATUS_PENDING,
            'database_created' => false,
        ]);

        $this->withHeader('Host', 'localhost')
            ->postJson('/api/v1/admin/tenants/'.$tenant->getKey().'/portability-export')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TENANT_NOT_READY');

        Queue::assertNotPushed(GenerateTenantPortabilityJob::class);
    }

    public function test_create_privacy_request_validates_kind(): void
    {
        $this->actingAsCentralAdmin();

        $this->withHeader('Host', 'localhost')
            ->postJson('/api/v1/admin/privacy/requests', [
                'kind' => 'invalid',
                'subject_type' => PrivacySubjectType::OTHER->value,
                'subject_email' => 'a@b.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kind');
    }

    private function actingAsCentralAdmin(): User
    {
        $user = User::query()->create([
            'name' => 'Admin Central',
            'email' => 'admin-'.uniqid().'@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->forceFill([
            'is_admin' => true,
            'admin_mfa_confirmed_at' => now(),
        ])->save();

        Sanctum::actingAs($user, ['admin', 'admin:mfa']);

        return $user;
    }
}
