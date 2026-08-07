<?php

namespace Tests\Unit\Services;

use App\Enums\Common\BillingAddonSubscriptionStatus;
use App\Enums\Common\BillingAddonType;
use App\Models\Central\BillingAddon;
use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonSubscription;
use App\Models\Central\TenantEntitlement;
use App\Services\PlanMatrixService;
use Database\Seeders\EntitlementSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanMatrixServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->seed(EntitlementSeeder::class);
    }

    public function test_it_resolves_features_from_db_plan(): void
    {
        $service = app(PlanMatrixService::class);
        $basico = Plan::where('slug', 'basico')->firstOrFail();
        $broker = Plan::where('slug', 'broker')->firstOrFail();

        $this->assertTrue($service->hasFeature($basico, 'dashboard.enabled'));
        $this->assertTrue($service->hasFeature($broker, 'dashboard.enabled'));
        $this->assertFalse($service->hasFeature($basico, 'committee'));
    }

    public function test_it_resolves_roadmap_features_by_plan(): void
    {
        $service = app(PlanMatrixService::class);

        $roadmapFeatures = [
            'prospection.terrain_cockpit',
            'prospection.pipeline_board',
            'collaboration.tasks',
            'collaboration.inbox',
            'prospection.comparison',
            'viabilities.scenarios',
            'dashboard.executive',
            'dashboard.goals',
            'dashboard.management',
            'projects.room',
            'committee.meeting',
            'committee.meeting_mode',
            'negotiation.deal_room',
            'legalization.control_center',
            'search.global',
            'workspace.saved_views',
            'workspace.personalization',
            'reports.builder',
            'territorial.map_comparison',
            'documents.intelligence',
            'ai.contextual',
            'mobile.capture',
            'onboarding.profile',
            'dashboard.personalization',
            'experience.accessibility',
        ];

        $enabledByPlan = [
            'broker' => [
                'prospection.terrain_cockpit',
                'prospection.pipeline_board',
                'collaboration.tasks',
                'collaboration.inbox',
                'workspace.saved_views',
                'mobile.capture',
                'onboarding.profile',
                'experience.accessibility',
            ],
            'basico' => [
                'prospection.terrain_cockpit',
                'prospection.pipeline_board',
                'collaboration.tasks',
                'collaboration.inbox',
                'prospection.comparison',
                'viabilities.scenarios',
                'search.global',
                'workspace.saved_views',
                'workspace.personalization',
                'mobile.capture',
                'onboarding.profile',
                'experience.accessibility',
            ],
            'master' => [
                'prospection.terrain_cockpit',
                'prospection.pipeline_board',
                'collaboration.tasks',
                'collaboration.inbox',
                'prospection.comparison',
                'viabilities.scenarios',
                'dashboard.executive',
                'dashboard.goals',
                'dashboard.management',
                'committee.meeting',
                'committee.meeting_mode',
                'legalization.control_center',
                'search.global',
                'workspace.saved_views',
                'workspace.personalization',
                'reports.builder',
                'documents.intelligence',
                'mobile.capture',
                'onboarding.profile',
                'dashboard.personalization',
                'experience.accessibility',
            ],
            'pro' => $roadmapFeatures,
        ];

        foreach ($enabledByPlan as $slug => $enabledFeatures) {
            $plan = Plan::where('slug', $slug)->firstOrFail();

            foreach ($enabledFeatures as $feature) {
                $this->assertTrue($service->hasFeature($plan, $feature), "{$slug}: {$feature}");
            }

            foreach (array_diff($roadmapFeatures, $enabledFeatures) as $feature) {
                $this->assertFalse($service->hasFeature($plan, $feature), "{$slug}: {$feature}");
            }
        }
    }

    public function test_it_preserves_ai_access_alongside_contextual_ai(): void
    {
        $service = app(PlanMatrixService::class);
        $master = Plan::where('slug', 'master')->firstOrFail();
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        $this->assertTrue($service->hasFeature($master, 'ai'));
        $this->assertFalse($service->hasFeature($master, 'ai.contextual'));
        $this->assertTrue($service->hasFeature($pro, 'ai'));
        $this->assertTrue($service->hasFeature($pro, 'ai.contextual'));
    }

    public function test_it_preserves_the_projects_room_alias_for_the_pro_plan(): void
    {
        $service = app(PlanMatrixService::class);
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        $this->assertTrue($service->hasFeature($pro, 'projects_room'));
        $this->assertTrue($service->hasFeature($pro, 'projects.room'));
    }

    public function test_it_resolves_limits_from_db_plan(): void
    {
        $service = app(PlanMatrixService::class);
        $basico = Plan::where('slug', 'basico')->firstOrFail();
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        $this->assertSame(3, $service->getLimit($basico, 'users'));
        $this->assertSame(-1, $service->getLimit($pro, 'users'));
        $this->assertTrue($service->isUnlimitedLimit($pro, 'users'));
    }

    public function test_it_returns_empty_matrix_for_plan_without_entitlements(): void
    {
        $service = app(PlanMatrixService::class);
        $plan = Plan::create(['name' => 'Empty', 'slug' => 'empty', 'price' => 0, 'sort_order' => 9, 'is_active' => true, 'trial_days' => 0]);

        $matrix = $service->resolve($plan);

        $this->assertSame([], $matrix['features']);
        $this->assertSame([], $matrix['limits']);
    }

    public function test_it_throws_for_unknown_slug(): void
    {
        $service = app(PlanMatrixService::class);

        $this->expectException(\InvalidArgumentException::class);

        $service->resolve('nonexistent');
    }

    public function test_it_adds_active_stripe_addons_and_applies_manual_extras_last(): void
    {
        $plan = Plan::where('slug', 'broker')->firstOrFail();
        $storage = Entitlement::where('key', 'storage_gb')->firstOrFail();
        $tenantId = Str::uuid()->toString();

        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Add-on tenant',
            'slug' => 'addon-'.Str::lower(Str::random(8)),
            'status' => Tenant::STATUS_ACTIVE,
            'plan_id' => $plan->getKey(),
            'database_created' => false,
            'trial_extended' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenant = Tenant::query()->findOrFail($tenantId);
        $addon = BillingAddon::query()->create([
            'slug' => 'storage-pack-test',
            'name' => 'Storage pack test',
            'type' => BillingAddonType::LIMIT_PACK,
            'stripe_price_id' => 'price_storage_test',
            'currency' => 'brl',
            'billing_interval' => 'month',
            'definition' => [
                'grants' => [
                    ['key' => 'storage_gb', 'type' => 'limit', 'unit_value' => 10],
                ],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        TenantAddonSubscription::query()->create([
            'tenant_id' => $tenant->getKey(),
            'billing_addon_id' => $addon->getKey(),
            'stripe_subscription_id' => 'sub_test',
            'stripe_subscription_item_id' => 'si_storage_test',
            'stripe_price_id' => 'price_storage_test',
            'quantity' => 2,
            'status' => BillingAddonSubscriptionStatus::ACTIVE,
        ]);

        TenantEntitlement::query()->create([
            'tenant_id' => $tenant->getKey(),
            'entitlement_id' => $storage->getKey(),
            'value' => 99,
            'price' => 0,
        ]);

        $matrix = app(PlanMatrixService::class)->resolveForTenant($tenant);

        // O extra Stripe soma sobre o plano, mas o extra manual é o override final.
        $this->assertSame(99, $matrix['limits']['storage_gb']);
    }
}
