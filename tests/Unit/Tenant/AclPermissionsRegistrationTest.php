<?php

namespace Tests\Unit\Tenant;

use App\Enums\Common\ModulesEnum;
use App\Enums\Common\RolesEnum;
use Database\Seeders\Tenant\RolePermissionSeeder;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class AclPermissionsRegistrationTest extends TestCase
{
    #[Test]
    public function it_generates_permissions_for_committee_and_negotiation_modules(): void
    {
        $seeder = new RolePermissionSeeder;
        $reflection = new ReflectionClass($seeder);
        $method = $reflection->getMethod('generateAllPermissions');
        $method->setAccessible(true);

        /** @var array<int, string> $permissions */
        $permissions = $method->invoke($seeder);

        $this->assertContains('committee.viewer', $permissions);
        $this->assertContains('committee.editor', $permissions);
        $this->assertContains('committee.manager', $permissions);
        $this->assertContains('negotiation.viewer', $permissions);
        $this->assertContains('negotiation.editor', $permissions);
        $this->assertContains('negotiation.manager', $permissions);
    }

    #[Test]
    public function all_role_templates_define_committee_and_negotiation_permissions(): void
    {
        foreach (RolesEnum::cases() as $role) {
            $path = database_path('rbacTemplates/'.strtolower($role->value).'.json');
            $this->assertFileExists($path, "Template ausente para {$role->value}");

            /** @var array{permissions: array<string, mixed>} $template */
            $template = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $permissions = $template['permissions'] ?? [];

            $this->assertArrayHasKey(
                ModulesEnum::COMMITTEE->value,
                $permissions,
                "Template {$role->value} sem permissões de committee."
            );
            $this->assertArrayHasKey(
                ModulesEnum::NEGOTIATION->value,
                $permissions,
                "Template {$role->value} sem permissões de negotiation."
            );
        }
    }
}
