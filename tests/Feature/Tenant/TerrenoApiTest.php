<?php

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TerrenoApiTest extends TestCase
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
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_crud_terreno_with_resources(): void
    {
        $createResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/terrenos', [
                'nome' => 'Terreno Alpha',
                'endereco' => 'Rua A',
                'estado' => 'SP',
            ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.nome', 'Terreno Alpha')
            ->assertJsonPath('data.created_by', $this->admin->id);

        $terrenoId = $createResponse->json('data.id');

        $this->actingAs($this->admin)
            ->getJson('/api/v1/terrenos')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/terrenos/{$terrenoId}")
            ->assertOk()
            ->assertJsonPath('data.nome', 'Terreno Alpha');

        $this->actingAs($this->admin)
            ->putJson("/api/v1/terrenos/{$terrenoId}", [
                'nome' => 'Terreno Beta',
            ])->assertOk()
            ->assertJsonPath('data.nome', 'Terreno Beta');

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/terrenos/{$terrenoId}")
            ->assertNoContent();

        $this->assertSoftDeleted('terrenos', ['id' => $terrenoId]);
    }

    public function test_admin_can_manage_terreno_infos_and_import_polygon(): void
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno Info',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/terrenos/{$terreno->id}/informacoes", [
                'descricao' => 'Primeira nota',
            ])->assertCreated()
            ->assertJsonPath('data.descricao', 'Primeira nota');

        $this->actingAs($this->admin)
            ->getJson("/api/v1/terrenos/{$terreno->id}/informacoes")
            ->assertOk()
            ->assertJsonPath('data.0.descricao', 'Primeira nota');

        $infoId = $terreno->informacoes()->firstOrFail()->id;

        $this->actingAs($this->admin)
            ->putJson("/api/v1/terrenos/informacoes/{$infoId}", [
                'descricao' => 'Nota atualizada',
            ])->assertOk()
            ->assertJsonPath('data.descricao', 'Nota atualizada');

        $kml = <<<KML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark>
      <Polygon>
        <outerBoundaryIs>
          <LinearRing>
            <coordinates>
              -46.7000,-23.5000,0 -46.7010,-23.5010,0 -46.7020,-23.5000,0 -46.7000,-23.5000,0
            </coordinates>
          </LinearRing>
        </outerBoundaryIs>
      </Polygon>
    </Placemark>
  </Document>
</kml>
KML;

        $file = UploadedFile::fake()->createWithContent('area.kml', $kml);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/terrenos/{$terreno->id}/import-kmz", [
                'arquivo' => $file,
            ])->assertOk()
            ->assertJsonPath('data.polygon_coords.0.lat', -23.5);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/terrenos/informacoes/{$infoId}")
            ->assertNoContent();
    }

    public function test_terreno_requests_require_authorization_and_validation(): void
    {
        $this->postJson('/api/v1/terrenos', ['nome' => 'Sem auth'])
            ->assertUnauthorized();

        $this->actingAs($this->admin)
            ->postJson('/api/v1/terrenos', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome']);
    }
}
