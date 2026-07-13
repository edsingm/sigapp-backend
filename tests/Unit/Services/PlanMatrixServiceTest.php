<?php

namespace Tests\Unit\Services;

use App\Models\Central\Plan;
use App\Services\PlanMatrixService;
use Database\Seeders\EntitlementSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
