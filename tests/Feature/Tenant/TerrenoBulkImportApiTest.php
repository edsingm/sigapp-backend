<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Enums\TerrenoImportStatus;
use App\Enums\TerrenoPolygonImportStatus;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnforcePlanLimits;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Http\Middleware\PermissionGate;
use App\Jobs\CommitTerrenoImportJob;
use App\Jobs\ParseTerrenoPolygonImportJob;
use App\Jobs\ValidateTerrenoImportJob;
use App\Models\Central\Tenant;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoImport;
use App\Models\Tenant\TerrenoPendingPolygon;
use App\Models\Tenant\TerrenoPolygonImport;
use App\Models\Tenant\User;
use App\Services\PlanMatrixService;
use App\Services\Tenant\TerrenoSpreadsheetService;
use App\Services\UsageMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TerrenoBulkImportApiTest extends TestCase
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
            CheckFeature::class,
            CheckSubscriptionStatus::class,
            EnforcePlanLimits::class,
            EnsureTenantContext::class,
            EnsureTenantUser::class,
            EnsureTenantAdmin::class,
            PermissionGate::class,
        ]);
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);
        $this->admin = User::query()->create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);

        Storage::fake('s3');
        Queue::fake();
    }

    public function test_planilha_valida_e_estagiada_antes_da_confirmacao(): void
    {
        $key = (string) Str::uuid();
        $response = $this->actingAs($this->admin)->post('/api/v1/terrenos/imports', [
            'idempotency_key' => $key,
            'arquivo' => $this->spreadsheet([
                ['nome' => 'Terreno Alfa', 'endereco' => 'Rua A', 'valor' => 'R$ 1.250,50'],
                ['nome' => 'Terreno Beta', 'endereco' => 'Rua B', 'valor' => 900],
            ]),
        ]);

        $response->assertAccepted()->assertJsonPath('data.status', TerrenoImportStatus::QUEUED->value);
        $import = TerrenoImport::query()->where('idempotency_key', $key)->firstOrFail();
        Queue::assertPushed(ValidateTerrenoImportJob::class);
        $this->actingAs($this->admin)->post('/api/v1/terrenos/imports', [
            'idempotency_key' => $key,
            'arquivo' => $this->spreadsheet([['nome' => 'Tentativa repetida']]),
        ])->assertAccepted()->assertJsonPath('data.id', $import->id);
        $this->assertDatabaseCount('terreno_imports', 1);
        Queue::assertPushed(ValidateTerrenoImportJob::class, 1);

        app()->call([new ValidateTerrenoImportJob($import->id), 'handle']);

        $import->refresh();
        $this->assertSame(TerrenoImportStatus::READY, $import->status);
        $this->assertSame(2, $import->valid_rows);
        $this->assertDatabaseCount('terrenos', 0);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/terrenos/imports/{$import->id}/confirm")
            ->assertAccepted()
            ->assertJsonPath('data.status', TerrenoImportStatus::IMPORTING->value);
        Queue::assertPushed(CommitTerrenoImportJob::class);

        $this->runCommitJob($import);

        $this->assertSame(TerrenoImportStatus::COMPLETED, $import->refresh()->status);
        $this->assertSame(2, $import->imported_rows);
        $this->assertDatabaseHas('terrenos', ['nome' => 'Terreno Alfa', 'created_by' => $this->admin->id]);
        $this->assertDatabaseHas('terrenos', ['nome' => 'Terreno Beta', 'created_by' => $this->admin->id]);
    }

    public function test_planilha_com_formula_fica_invalida_e_nao_cria_terrenos(): void
    {
        $key = (string) Str::uuid();
        $this->actingAs($this->admin)->post('/api/v1/terrenos/imports', [
            'idempotency_key' => $key,
            'arquivo' => $this->spreadsheet([
                ['nome' => 'Terreno Fórmula', 'valor' => '=100+20'],
            ]),
        ])->assertAccepted();
        $import = TerrenoImport::query()->where('idempotency_key', $key)->firstOrFail();

        app()->call([new ValidateTerrenoImportJob($import->id), 'handle']);

        $import->refresh();
        $this->assertSame(TerrenoImportStatus::INVALID, $import->status);
        $this->assertSame(1, $import->invalid_rows);
        $this->assertDatabaseCount('terrenos', 0);
        $this->actingAs($this->admin)
            ->postJson("/api/v1/terrenos/imports/{$import->id}/confirm")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'TERRAIN_IMPORT_HAS_ERRORS');
    }

    public function test_confirmacao_faz_rollback_integral_se_duplicata_surgir_apos_validacao(): void
    {
        $key = (string) Str::uuid();
        $this->actingAs($this->admin)->post('/api/v1/terrenos/imports', [
            'idempotency_key' => $key,
            'arquivo' => $this->spreadsheet([
                ['nome' => 'Terreno Primeiro', 'endereco' => 'Rua A'],
                ['nome' => 'Terreno Concorrente', 'endereco' => 'Rua B'],
            ]),
        ])->assertAccepted();
        $import = TerrenoImport::query()->where('idempotency_key', $key)->firstOrFail();
        app()->call([new ValidateTerrenoImportJob($import->id), 'handle']);
        $this->assertSame(TerrenoImportStatus::READY, $import->refresh()->status);

        Terreno::query()->create([
            'nome' => 'Terreno Concorrente',
            'endereco' => 'Rua B',
            'created_by' => $this->admin->id,
        ]);
        $this->actingAs($this->admin)
            ->postJson("/api/v1/terrenos/imports/{$import->id}/confirm")
            ->assertAccepted();

        $this->runCommitJob($import);

        $this->assertSame(TerrenoImportStatus::FAILED, $import->refresh()->status);
        $this->assertDatabaseMissing('terrenos', ['nome' => 'Terreno Primeiro']);
        $this->assertDatabaseCount('terrenos', 1);
    }

    public function test_multiplos_kml_geram_poligonos_no_mapa_e_permitem_vinculo_sem_sobrescrita(): void
    {
        $key = (string) Str::uuid();
        $this->actingAs($this->admin)->post('/api/v1/terrenos/polygon-imports', [
            'idempotency_key' => $key,
            'arquivos' => [
                UploadedFile::fake()->createWithContent('norte.kml', $this->kml('Norte', -46.7, -23.5)),
                UploadedFile::fake()->createWithContent('sul.kml', $this->kml('Sul', -46.8, -23.6)),
            ],
        ])->assertAccepted();
        $import = TerrenoPolygonImport::query()->where('idempotency_key', $key)->firstOrFail();

        app()->call([new ParseTerrenoPolygonImportJob($import->id), 'handle']);

        $import->refresh();
        $this->assertSame(TerrenoPolygonImportStatus::COMPLETED, $import->status);
        $this->assertDatabaseCount('terreno_pending_polygons', 2);
        $this->actingAs($this->admin)
            ->getJson('/api/v1/terrenos/polygons?bbox=-47,-24,-46,-23')
            ->assertOk()
            ->assertJsonCount(2, 'data.features')
            ->assertJsonPath('data.type', 'FeatureCollection');

        $terrain = Terreno::factory()->createOne([
            'created_by' => $this->admin->id,
            'polygon_coords' => null,
        ]);
        $polygon = TerrenoPendingPolygon::query()->orderBy('id')->firstOrFail();
        $this->actingAs($this->admin)
            ->postJson("/api/v1/terrenos/polygons/{$polygon->id}/link", ['terreno_id' => $terrain->id])
            ->assertOk()
            ->assertJsonPath('data.properties.terreno_id', $terrain->id);
        $this->assertNotEmpty($terrain->refresh()->polygon_coords);

        $otherPolygon = TerrenoPendingPolygon::query()->where('id', '<>', $polygon->id)->firstOrFail();
        $this->actingAs($this->admin)
            ->postJson("/api/v1/terrenos/polygons/{$otherPolygon->id}/link", ['terreno_id' => $terrain->id])
            ->assertConflict()
            ->assertJsonPath('error.code', 'TERRAIN_ALREADY_HAS_POLYGON');
    }

    /** @param list<array<string, int|string>> $rows */
    private function spreadsheet(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Terrenos');
        $sheet->fromArray(TerrenoSpreadsheetService::HEADERS, null, 'A1');
        foreach ($rows as $offset => $row) {
            $rowNumber = $offset + 2;
            foreach ($row as $header => $value) {
                $column = array_search($header, TerrenoSpreadsheetService::HEADERS, true);
                if (! is_int($column)) {
                    throw new RuntimeException("Cabeçalho de teste inválido: {$header}");
                }
                $coordinate = $sheet->getCell([$column + 1, $rowNumber])->getCoordinate();
                if (is_string($value) && str_starts_with($value, '=')) {
                    $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_FORMULA);
                } else {
                    $sheet->setCellValue($coordinate, $value);
                }
            }
        }
        $basePath = tempnam(sys_get_temp_dir(), 'sigapp-import-api-');
        if ($basePath === false) {
            throw new RuntimeException('Não foi possível criar planilha de teste.');
        }
        $path = $basePath.'.xlsx';
        @unlink($basePath);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'terrenos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    private function kml(string $name, float $lng, float $lat): string
    {
        $south = $lat - 0.01;
        $east = $lng + 0.01;

        return <<<KML
        <kml xmlns="http://www.opengis.net/kml/2.2"><Placemark><name>{$name}</name><Polygon>
          <outerBoundaryIs><LinearRing><coordinates>
            {$lng},{$lat},0 {$lng},{$south},0 {$east},{$south},0 {$lng},{$lat},0
          </coordinates></LinearRing></outerBoundaryIs>
        </Polygon></Placemark></kml>
        KML;
    }

    private function runCommitJob(TerrenoImport $import): void
    {
        $tenant = new Tenant;
        $tenant->setAttribute('id', 'test-tenant');
        $tenancy = tenancy();
        $tenancy->tenant = $tenant;
        $tenancy->initialized = true;

        $this->mock(UsageMetricsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getTerrenoCount')->once()->andReturn(0);
        });
        $this->mock(PlanMatrixService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isUnlimitedLimitForTenant')->once()->andReturn(true);
        });

        try {
            app()->call([new CommitTerrenoImportJob($import->id), 'handle']);
        } finally {
            $tenancy->tenant = null;
            $tenancy->initialized = false;
        }
    }
}
