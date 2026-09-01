<?php

declare(strict_types=1);

namespace Tests\Unit\Plans;

use App\Enums\Common\EntitlementType;
use Database\Seeders\EntitlementSeeder;
use ReflectionMethod;
use Tests\TestCase;

class EntitlementSeederMatrixTest extends TestCase
{
    public function test_module_and_workflow_cut_keys_do_not_overlap(): void
    {
        foreach (['broker', 'basico', 'master', 'pro'] as $slug) {
            $overlap = array_values(array_intersect(
                array_keys($this->invokeSeeder('moduleFeatureMatrix', $slug)),
                array_keys($this->invokeSeeder('workflowCutFeatureMatrix', $slug)),
            ));

            self::assertSame([], $overlap, "Overlap no plano {$slug}");
        }
    }

    public function test_composed_matrix_covers_the_entitlement_catalog(): void
    {
        $defs = $this->invokeSeeder('entitlementDefinitions');
        $featureKeys = [];
        $limitKeys = [];

        foreach ($defs as $def) {
            if ($def['type'] === EntitlementType::FEATURE) {
                $featureKeys[] = $def['key'];
            } else {
                $limitKeys[] = $def['key'];
            }
        }

        $planMatrix = $this->invokeSeeder('planMatrix');

        foreach ($planMatrix as $slug => $matrix) {
            self::assertEqualsCanonicalizing(
                $featureKeys,
                array_keys($matrix['features']),
                "Features do plano {$slug} fora do catálogo."
            );
            self::assertEqualsCanonicalizing(
                $limitKeys,
                array_keys($matrix['limits']),
                "Limites do plano {$slug} fora do catálogo."
            );
        }
    }

    public function test_projects_planning_belongs_to_the_workflow_cut(): void
    {
        $module = $this->invokeSeeder('moduleFeatureMatrix', 'pro');
        $workflow = $this->invokeSeeder('workflowCutFeatureMatrix', 'pro');

        self::assertArrayHasKey('projects.enabled', $module);
        self::assertArrayNotHasKey('projects.planning', $module);
        self::assertArrayHasKey('projects.planning', $workflow);
        self::assertTrue($workflow['projects.planning']);
        self::assertFalse($this->invokeSeeder('workflowCutFeatureMatrix', 'master')['projects.planning']);
    }

    private function invokeSeeder(string $method, mixed ...$args): mixed
    {
        $seeder = new EntitlementSeeder;

        return new ReflectionMethod($seeder, $method)->invoke($seeder, ...$args);
    }
}
