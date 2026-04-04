<?php

namespace Tests\Feature\Modules;

use App\Enums\Common\ModulesEnum;
use App\Enums\Common\SectorsEnum;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Central\Modules\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulesApiTest extends TestCase
{
    use RefreshDatabase;

    private function getModules(): \Illuminate\Testing\TestResponse
    {
        // Bypass tenant + logging middlewares; we test controller/service logic only
        return $this
            ->withoutMiddleware()
            ->getJson('/api/v1/modules');
    }

    // ─── Basic Response ───────────────────────────────────────────────────────

    public function test_it_returns_200_with_success_flag(): void
    {
        $this->seed(\Database\Seeders\ModulesSeeder::class);

        $response = $this->getModules();

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_it_returns_empty_data_when_no_active_modules_exist(): void
    {
        // No modules seeded
        $response = $this->getModules();

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame([], $response->json('data'));
    }

    // ─── Response Structure ───────────────────────────────────────────────────

    public function test_response_has_sector_grouped_structure(): void
    {
        $this->seed(\Database\Seeders\ModulesSeeder::class);

        $response = $this->getModules();

        $response->assertOk()->assertJsonStructure([
            'data' => [
                '*' => [
                    'sector'  => ['slug', 'label', 'order'],
                    'modules' => [],
                ],
            ],
        ]);
    }

    public function test_module_has_expected_fields(): void
    {
        Modules::create(['slug' => ModulesEnum::DASHBOARD->value, 'active' => true, 'order' => 10]);

        $response = $this->getModules();

        $response->assertOk();
        $firstModule = $response->json('data.0.modules.0');

        $this->assertNotNull($firstModule);

        foreach (['slug', 'name', 'order', 'active', 'submodules'] as $field) {
            $this->assertArrayHasKey($field, $firstModule, "Field [{$field}] missing from module response");
        }
    }

    // ─── Sensitive Fields Not Exposed ─────────────────────────────────────────

    public function test_module_does_not_expose_internal_id(): void
    {
        Modules::create(['slug' => ModulesEnum::DASHBOARD->value, 'active' => true, 'order' => 10]);

        $response = $this->getModules();

        $response->assertOk();
        $module = $response->json('data.0.modules.0');

        $this->assertArrayNotHasKey('id', $module);
    }

    public function test_module_does_not_expose_timestamps(): void
    {
        Modules::create(['slug' => ModulesEnum::DASHBOARD->value, 'active' => true, 'order' => 10]);

        $response = $this->getModules();

        $response->assertOk();
        $module = $response->json('data.0.modules.0');

        $this->assertArrayNotHasKey('created_at', $module);
        $this->assertArrayNotHasKey('updated_at', $module);
    }

    public function test_module_does_not_expose_raw_sector_or_submodule_enum_objects(): void
    {
        Modules::create(['slug' => ModulesEnum::DASHBOARD->value, 'active' => true, 'order' => 10]);

        $response = $this->getModules();

        $response->assertOk();
        $rawData = $response->content();

        // Ensure no PHP object references leak into JSON
        $this->assertStringNotContainsString('Stancl\\', $rawData);
        $this->assertStringNotContainsString('App\\Enums', $rawData);
    }

    // ─── Only Active Modules ──────────────────────────────────────────────────

    public function test_inactive_modules_are_excluded(): void
    {
        Modules::create(['slug' => ModulesEnum::DASHBOARD->value, 'active' => true, 'order' => 10]);
        Modules::create(['slug' => ModulesEnum::ADMIN->value, 'active' => false, 'order' => 99]);

        $response = $this->getModules();

        $response->assertOk();

        $allSlugs = collect($response->json('data'))
            ->flatMap(fn($sector) => collect($sector['modules'])->pluck('slug'))
            ->all();

        $this->assertContains(ModulesEnum::DASHBOARD->value, $allSlugs);
        $this->assertNotContains(ModulesEnum::ADMIN->value, $allSlugs);
    }

    // ─── Submodules ───────────────────────────────────────────────────────────

    public function test_prospection_module_has_serialized_submodules(): void
    {
        Modules::create(['slug' => ModulesEnum::PROSPECTION->value, 'active' => true, 'order' => 20]);

        $response = $this->getModules();

        $response->assertOk();

        $allModules = collect($response->json('data'))
            ->flatMap(fn($sector) => $sector['modules'])
            ->keyBy('slug');

        $prospection = $allModules->get(ModulesEnum::PROSPECTION->value);
        $this->assertNotNull($prospection);
        $this->assertNotEmpty($prospection['submodules']);

        foreach ($prospection['submodules'] as $sub) {
            $this->assertArrayHasKey('slug', $sub);
            $this->assertArrayHasKey('label', $sub);
        }
    }

    public function test_dashboard_module_has_empty_submodules(): void
    {
        Modules::create(['slug' => ModulesEnum::DASHBOARD->value, 'active' => true, 'order' => 10]);

        $response = $this->getModules();

        $response->assertOk();

        $dashboard = null;
        foreach ($response->json('data') as $sector) {
            foreach ($sector['modules'] as $mod) {
                if ($mod['slug'] === ModulesEnum::DASHBOARD->value) {
                    $dashboard = $mod;
                }
            }
        }

        $this->assertNotNull($dashboard);
        $this->assertSame([], $dashboard['submodules']);
    }

    // ─── Sector Ordering ─────────────────────────────────────────────────────

    public function test_sectors_are_returned_in_ascending_order(): void
    {
        $this->seed(\Database\Seeders\ModulesSeeder::class);

        $response = $this->getModules();

        $response->assertOk();

        $sectors     = $response->json('data');
        $sectorOrders = array_column(array_column($sectors, 'sector'), 'order');

        $sorted = $sectorOrders;
        sort($sorted);
        $this->assertSame(array_values($sectorOrders), array_values($sorted));
    }
}
