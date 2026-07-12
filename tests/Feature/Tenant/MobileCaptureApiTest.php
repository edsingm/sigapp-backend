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
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MobileCaptureApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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
            'name' => 'Mobile Capture Admin',
            'email' => 'mobile-capture-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);
        Storage::fake('s3');
    }

    public function test_capture_is_idempotent_versioned_and_committable(): void
    {
        $clientId = (string) Str::uuid();
        $this->actingAs($this->admin)->postJson('/api/v1/mobile/captures', [
            'client_id' => $clientId,
            'payload' => ['nome' => 'Terreno capturado'],
            'location' => ['latitude' => -23.55, 'longitude' => -46.63, 'accuracy' => 4.2],
        ])->assertOk()->assertJsonPath('data.version', 1);

        $this->actingAs($this->admin)->postJson('/api/v1/mobile/captures', [
            'client_id' => $clientId,
            'payload' => ['nome' => 'Tentativa repetida'],
        ])->assertOk()->assertJsonPath('data.payload.nome', 'Terreno capturado');

        $this->actingAs($this->admin)->putJson("/api/v1/mobile/captures/{$clientId}", [
            'base_version' => 1,
            'payload' => ['observacoes' => 'Anotação offline'],
        ])->assertOk()->assertJsonPath('data.version', 2);

        $this->actingAs($this->admin)->postJson("/api/v1/mobile/captures/{$clientId}/commit", [
            'base_version' => 1,
        ])->assertStatus(409)->assertJsonPath('error.code', 'CAPTURE_CONFLICT');

        $commit = $this->actingAs($this->admin)->postJson("/api/v1/mobile/captures/{$clientId}/commit", [
            'base_version' => 2,
        ])->assertOk()->assertJsonPath('data.status', 'committed');

        $this->assertDatabaseHas('mobile_captures', ['client_id' => $clientId, 'status' => 'committed']);
        $this->assertDatabaseHas('terrenos', ['nome' => 'Terreno capturado']);
        $this->assertNotNull($commit->json('data.terreno_id'));
    }

    public function test_capture_attachment_is_deduplicated_by_checksum(): void
    {
        $clientId = (string) Str::uuid();
        $this->actingAs($this->admin)->postJson('/api/v1/mobile/captures', [
            'client_id' => $clientId,
            'payload' => ['nome' => 'Terreno com foto'],
        ])->assertOk();

        $file = UploadedFile::fake()->image('fachada.jpg');
        $this->actingAs($this->admin)->post("/api/v1/mobile/captures/{$clientId}/attachments", [
            'arquivo' => $file,
        ])->assertOk();
        $this->actingAs($this->admin)->post("/api/v1/mobile/captures/{$clientId}/attachments", [
            'arquivo' => UploadedFile::fake()->createWithContent('fachada.jpg', $file->getContent()),
        ])->assertOk();

        $this->assertDatabaseCount('mobile_capture_attachments', 1);
    }
}
