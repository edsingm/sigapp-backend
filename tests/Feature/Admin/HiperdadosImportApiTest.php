<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\HiperdadosImportStatus;
use App\Jobs\CommitHiperdadosImportJob;
use App\Jobs\FetchHiperdadosImportJob;
use App\Models\Central\HiperdadosImport;
use App\Models\Central\Tenant;
use App\Models\Tenant\Terreno;
use App\Models\User;
use App\Services\Admin\HiperdadosImportService;
use App\Services\Admin\HiperdadosPortalScrapeService;
use App\Services\Admin\HiperdadosTerrenoCommitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HiperdadosImportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_start_import_and_job_is_queued(): void
    {
        $this->actingAsCentralAdmin();
        Queue::fake();

        $response = $this->adminJson('post', '/api/v1/admin/hiperdados-imports', [
            'username' => 'cliente@portal.com',
            'password' => 'secret-password',
            'limit' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', HiperdadosImportStatus::Queued->value)
            ->assertJsonPath('data.portal_username', 'cliente@portal.com')
            ->assertJsonPath('data.limit', 10)
            ->assertJsonMissingPath('data.credentials_encrypted')
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('hiperdados_imports', [
            'portal_username' => 'cliente@portal.com',
            'status' => HiperdadosImportStatus::Queued->value,
            'limit_count' => 10,
        ]);

        Queue::assertPushed(FetchHiperdadosImportJob::class);
    }

    public function test_start_requires_credentials(): void
    {
        $this->actingAsCentralAdmin();

        $this->adminJson('post', '/api/v1/admin/hiperdados-imports', [
            'username' => '',
            'password' => '',
        ])->assertUnprocessable();
    }

    public function test_non_admin_cannot_start_import(): void
    {
        $user = $this->makeUser(['is_admin' => false]);
        Sanctum::actingAs($user, ['*']);

        $this->adminJson('post', '/api/v1/admin/hiperdados-imports', [
            'username' => 'cliente@portal.com',
            'password' => 'secret',
        ])->assertForbidden();
    }

    public function test_process_fetch_marks_ready_and_stores_payload(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $admin = $this->actingAsCentralAdmin();
        $import = HiperdadosImport::query()->create([
            'uuid' => (string) Str::uuid(),
            'status' => HiperdadosImportStatus::Queued,
            'created_by' => $admin->id,
            'portal_username' => 'cliente@portal.com',
            'credentials_encrypted' => Crypt::encryptString(json_encode([
                'username' => 'cliente@portal.com',
                'password' => 'secret',
            ], JSON_THROW_ON_ERROR)),
            'limit_count' => 2,
        ]);

        $lista = [
            [
                'id' => '1',
                'nome' => 'Terreno A',
                'gestor' => 'Ana',
                'status' => 'Análise',
                'poligono' => [['lat' => 1.0, 'lng' => 2.0]],
            ],
            [
                'id' => '2',
                'nome' => 'Terreno B',
                'gestor' => 'Bob',
                'status' => 'Descartado',
                'poligono' => [],
            ],
        ];

        $this->mock(HiperdadosPortalScrapeService::class, function ($mock) use ($lista): void {
            $mock->shouldReceive('extractList')
                ->once()
                ->andReturn($lista);

            $mock->shouldReceive('enrichBatch')
                ->once()
                ->andReturn([
                    'items' => [
                        array_merge($lista[0], [
                            'ficha' => ['cidade' => 'Bauru', 'uf' => 'SP'],
                            'formulario' => [],
                            'corretores' => [],
                        ]),
                        array_merge($lista[1], [
                            'ficha' => null,
                            'formulario' => [],
                            'corretores' => [],
                        ]),
                    ],
                    'failures' => [],
                ]);
        });

        $continue = app(HiperdadosImportService::class)->processFetch($import->id);
        $this->assertFalse($continue);

        $import->refresh();
        $this->assertSame(HiperdadosImportStatus::Ready, $import->status);
        $this->assertSame(2, $import->total_count);
        $this->assertNull($import->credentials_encrypted);
        $this->assertNotNull($import->storage_path);
        Storage::disk('local')->assertExists((string) $import->storage_path);
    }

    public function test_preview_returns_payload_items(): void
    {
        Storage::fake('local');
        $admin = $this->actingAsCentralAdmin();

        $path = 'imports/hiperdados/preview-test.json';
        Storage::disk('local')->put($path, json_encode([
            ['id' => '9', 'nome' => 'Preview Terreno', 'status' => 'Análise'],
        ], JSON_THROW_ON_ERROR));

        $import = HiperdadosImport::query()->create([
            'uuid' => (string) Str::uuid(),
            'status' => HiperdadosImportStatus::Ready,
            'created_by' => $admin->id,
            'portal_username' => 'cliente@portal.com',
            'total_count' => 1,
            'processed_count' => 1,
            'storage_disk' => 'local',
            'storage_path' => $path,
        ]);

        $this->adminJson('get', "/api/v1/admin/hiperdados-imports/{$import->uuid}/preview?limit=10")
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.nome', 'Preview Terreno');
    }

    public function test_commit_requires_ready_status(): void
    {
        $this->actingAsCentralAdmin();
        Queue::fake();

        $import = HiperdadosImport::query()->create([
            'uuid' => (string) Str::uuid(),
            'status' => HiperdadosImportStatus::Queued,
            'portal_username' => 'cliente@portal.com',
        ]);

        $this->adminJson('post', "/api/v1/admin/hiperdados-imports/{$import->uuid}/commit", [
            'tenant_id' => 'tenant-x',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'HIPERDADOS_IMPORT_NOT_READY');
    }

    public function test_commit_queues_job_when_ready(): void
    {
        Storage::fake('local');
        $this->actingAsCentralAdmin();
        Queue::fake();

        $path = 'imports/hiperdados/commit-test.json';
        Storage::disk('local')->put($path, json_encode([
            ['id' => '1', 'nome' => 'T1', 'status' => 'Análise', 'ficha' => [], 'formulario' => [], 'corretores' => []],
        ], JSON_THROW_ON_ERROR));

        $tenant = Tenant::create([
            'name' => 'Tenant Destino',
            'slug' => 'tenant-destino-'.uniqid(),
            'status' => Tenant::STATUS_ACTIVE,
            'admin_name' => 'Admin',
            'admin_email' => 'admin@destino.com',
            'admin_password' => 'password123',
            'database_created' => true,
        ]);

        $import = HiperdadosImport::query()->create([
            'uuid' => (string) Str::uuid(),
            'status' => HiperdadosImportStatus::Ready,
            'portal_username' => 'cliente@portal.com',
            'total_count' => 1,
            'storage_disk' => 'local',
            'storage_path' => $path,
        ]);

        $this->adminJson('post', "/api/v1/admin/hiperdados-imports/{$import->uuid}/commit", [
            'tenant_id' => $tenant->id,
        ])->assertOk()
            ->assertJsonPath('data.status', HiperdadosImportStatus::Committing->value)
            ->assertJsonPath('data.tenant_id', $tenant->id);

        Queue::assertPushed(CommitHiperdadosImportJob::class);
    }

    public function test_commit_service_imports_terrenos(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/hiperdados_terrenos_sample.json')),
            true
        );
        $this->assertIsArray($fixture);

        $result = app(HiperdadosTerrenoCommitService::class)->commit($fixture);

        $this->assertGreaterThan(0, $result['imported']);
        $this->assertDatabaseHas('terrenos', [
            'nome' => $fixture[0]['nome'],
        ]);
        $this->assertInstanceOf(Terreno::class, Terreno::query()->where('nome', $fixture[0]['nome'])->first());
    }

    private function actingAsCentralAdmin(): User
    {
        $user = $this->makeUser(['is_admin' => true]);
        Sanctum::actingAs($user, ['admin', 'admin:mfa']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUser(array $attributes = []): User
    {
        $user = User::create([
            'name' => $attributes['name'] ?? 'Admin Central',
            'email' => $attributes['email'] ?? ('user-'.uniqid().'@example.com'),
            'password' => $attributes['password'] ?? Hash::make('password123'),
        ]);

        $user->forceFill([
            'is_admin' => $attributes['is_admin'] ?? true,
            'admin_mfa_confirmed_at' => ($attributes['is_admin'] ?? true) ? now() : null,
        ])->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function adminJson(string $method, string $uri, array $payload = [])
    {
        return $this
            ->withHeader('Host', 'localhost')
            ->{$method.'Json'}($uri, $payload);
    }
}
