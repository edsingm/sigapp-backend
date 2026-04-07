<?php

namespace Tests\Unit\Modules;

use App\Enums\Common\ModulesEnum;
use App\Enums\Common\SectorsEnum;
use App\Models\Central\Modules\Modules;
use App\Services\Modules\ModulesService;
use Database\Seeders\ModulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulesServiceTest extends TestCase
{
    use RefreshDatabase;

    private ModulesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ModulesService::class);
    }

    public function test_it_returns_only_active_modules(): void
    {
        Modules::create(['slug' => ModulesEnum::DASHBOARD->value, 'active' => true, 'order' => 10]);
        Modules::create(['slug' => ModulesEnum::ADMIN->value, 'active' => false, 'order' => 99]);

        $grouped = $this->service->getAllModules();

        $allSlugs = collect($grouped)->flatten(1)->pluck('slug')->all();
        $this->assertContains(ModulesEnum::DASHBOARD->value, $allSlugs);
        $this->assertNotContains(ModulesEnum::ADMIN->value, $allSlugs);
    }

    public function test_it_groups_modules_by_sector(): void
    {
        // DASHBOARD → SectorsEnum::PRINCIPAL
        // ADMIN → SectorsEnum::ADMINISTRATION
        Modules::create(['slug' => ModulesEnum::DASHBOARD->value, 'active' => true, 'order' => 10]);
        Modules::create(['slug' => ModulesEnum::ADMIN->value, 'active' => true, 'order' => 99]);

        $grouped = $this->service->getAllModules();

        $this->assertArrayHasKey(SectorsEnum::PRINCIPAL->value, $grouped);
        $this->assertArrayHasKey(SectorsEnum::ADMINISTRATION->value, $grouped);
    }

    public function test_it_returns_modules_ordered_by_order_column(): void
    {
        Modules::create(['slug' => ModulesEnum::ADMIN->value,      'active' => true, 'order' => 99]);
        Modules::create(['slug' => ModulesEnum::DASHBOARD->value,  'active' => true, 'order' => 10]);
        Modules::create(['slug' => ModulesEnum::PROSPECTION->value, 'active' => true, 'order' => 20]);

        $grouped = $this->service->getAllModules();

        $allModules = collect($grouped)->flatten(1)->values();
        $orders = $allModules->pluck('order')->all();

        $sorted = $orders;
        sort($sorted);
        $this->assertSame(array_values($orders), array_values($sorted));
    }

    public function test_it_returns_empty_array_when_no_modules_are_active(): void
    {
        Modules::create(['slug' => ModulesEnum::DASHBOARD->value, 'active' => false, 'order' => 10]);

        $grouped = $this->service->getAllModules();

        $this->assertEmpty($grouped);
    }

    public function test_sectors_are_sorted_by_sector_order(): void
    {
        // Seed all modules to get full sector grouping
        $this->seed(ModulesSeeder::class);

        $grouped = $this->service->getAllModules();
        $sectorKeys = array_keys($grouped);

        $expectedOrder = collect(SectorsEnum::cases())
            ->sortBy(fn ($s) => $s->order())
            ->filter(fn ($s) => in_array($s->value, $sectorKeys))
            ->map(fn ($s) => $s->value)
            ->values()
            ->all();

        $this->assertSame($expectedOrder, $sectorKeys);
    }
}
