<?php

declare(strict_types=1);

namespace Tests\Feature\Plans;

use App\Enums\Common\EntitlementScope;
use App\Enums\Common\EntitlementType;
use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectEntitlementAliasMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_alias_migration_preserves_plan_values_under_canonical_keys(): void
    {
        $plan = Plan::query()->create([
            'name' => 'Legacy',
            'slug' => 'legacy',
            'price' => 0,
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 99,
        ]);
        $legacy = Entitlement::query()->create([
            'key' => 'projects_room',
            'label' => 'Legacy',
            'type' => EntitlementType::FEATURE,
            'scope' => EntitlementScope::API,
            'default_value' => false,
        ]);
        DB::table('plan_entitlements')->insert([
            'plan_id' => $plan->id,
            'entitlement_id' => $legacy->id,
            'value' => json_encode(true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_07_29_000002_migrate_project_entitlement_aliases.php');
        $migration->up();

        $canonical = Entitlement::query()->where('key', 'projects.enabled')->firstOrFail();
        self::assertDatabaseMissing('entitlements', ['key' => 'projects_room']);
        self::assertDatabaseHas('plan_entitlements', [
            'plan_id' => $plan->id,
            'entitlement_id' => $canonical->id,
            'value' => 'true',
        ]);
    }

    public function test_existing_canonical_link_wins_without_unique_constraint_failure(): void
    {
        $plan = Plan::query()->create([
            'name' => 'Mixed',
            'slug' => 'mixed',
            'price' => 0,
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 98,
        ]);
        $legacy = $this->feature('projects_room', 'Legacy');
        $canonical = $this->feature('projects.enabled', 'Canonical', 'preexisting');

        foreach ([[$legacy->id, true], [$canonical->id, false]] as [$entitlementId, $value]) {
            DB::table('plan_entitlements')->insert([
                'plan_id' => $plan->id,
                'entitlement_id' => $entitlementId,
                'value' => json_encode($value),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration = require database_path('migrations/2026_07_29_000002_migrate_project_entitlement_aliases.php');
        $migration->up();

        self::assertSame(1, DB::table('plan_entitlements')->where('plan_id', $plan->id)->count());
        self::assertDatabaseHas('plan_entitlements', [
            'plan_id' => $plan->id,
            'entitlement_id' => $canonical->id,
            'value' => 'false',
        ]);

        $migration->down();

        self::assertDatabaseHas('entitlements', [
            'id' => $canonical->id,
            'key' => 'projects.enabled',
            'description' => 'preexisting',
        ]);
    }

    public function test_down_removes_only_canonical_entitlement_created_by_migration(): void
    {
        $legacy = $this->feature('projects_room', 'Legacy');
        $migration = require database_path('migrations/2026_07_29_000002_migrate_project_entitlement_aliases.php');

        $migration->up();
        self::assertDatabaseMissing('entitlements', ['id' => $legacy->id]);

        $migration->down();

        self::assertDatabaseHas('entitlements', ['key' => 'projects_room']);
        self::assertDatabaseMissing('entitlements', ['key' => 'projects.enabled']);
    }

    private function feature(string $key, string $label, ?string $description = null): Entitlement
    {
        return Entitlement::query()->create([
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'type' => EntitlementType::FEATURE,
            'scope' => EntitlementScope::API,
            'default_value' => false,
        ]);
    }
}
