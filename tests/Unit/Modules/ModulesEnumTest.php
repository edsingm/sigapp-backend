<?php

namespace Tests\Unit\Modules;

use App\Enums\Common\ModulesEnum;
use App\Enums\Common\SectorsEnum;
use App\Enums\Common\SubmodulesEnum;
use Tests\TestCase;

class ModulesEnumTest extends TestCase
{
    public function test_all_cases_have_a_non_empty_label(): void
    {
        foreach (ModulesEnum::cases() as $module) {
            $this->assertNotEmpty($module->label(), "Module [{$module->value}] has empty label");
        }
    }

    public function test_all_cases_have_a_valid_sector(): void
    {
        foreach (ModulesEnum::cases() as $module) {
            $sector = $module->sector();
            $this->assertInstanceOf(SectorsEnum::class, $sector, "Module [{$module->value}] returned invalid sector");
        }
    }

    public function test_all_cases_have_a_positive_order(): void
    {
        foreach (ModulesEnum::cases() as $module) {
            $this->assertGreaterThan(0, $module->order(), "Module [{$module->value}] has non-positive order");
        }
    }

    public function test_dashboard_belongs_to_principal_sector(): void
    {
        $this->assertSame(SectorsEnum::PRINCIPAL, ModulesEnum::DASHBOARD->sector());
    }

    public function test_admin_belongs_to_administration_sector(): void
    {
        $this->assertSame(SectorsEnum::ADMINISTRATION, ModulesEnum::ADMIN->sector());
    }

    public function test_prospection_has_submodules(): void
    {
        $this->assertTrue(ModulesEnum::PROSPECTION->hasSubmodules());

        $submodules = ModulesEnum::PROSPECTION->submodules();
        $this->assertNotEmpty($submodules);
        $this->assertContainsOnlyInstancesOf(SubmodulesEnum::class, $submodules);
    }

    public function test_dashboard_has_no_submodules(): void
    {
        $this->assertFalse(ModulesEnum::DASHBOARD->hasSubmodules());
        $this->assertSame([], ModulesEnum::DASHBOARD->submodules());
    }

    public function test_enum_can_be_resolved_from_its_value(): void
    {
        $this->assertSame(ModulesEnum::PROSPECTION, ModulesEnum::from('prospection'));
        $this->assertSame(ModulesEnum::ADMIN, ModulesEnum::from('admin'));
    }
}
