<?php

namespace Tests\Feature\Tenant;

use Tests\TestCase;

class TenantControllersTest extends TestCase
{
    public function test_tenant_controllers_existem(): void
    {
        $controllers = [
            'App\Http\Controllers\Api\V1\Tenant\DashboardController',
            'App\Http\Controllers\Api\V1\Tenant\TenantController',
            'App\Http\Controllers\Api\V1\Tenant\UserController',
            'App\Http\Controllers\Api\V1\Tenant\CorretoresExternosController',
            'App\Http\Controllers\Api\V1\Tenant\MobileNotificationController',
            'App\Http\Controllers\Api\V1\Tenant\PremissasViabilidadeController',
            'App\Http\Controllers\Api\V1\Tenant\TerrenosExportController',
            'App\Http\Controllers\Api\V1\Tenant\AiMonitorController',
            'App\Http\Controllers\Api\V1\Tenant\AiPredictiveAnalysisController',
            'App\Http\Controllers\Api\V1\Tenant\AiScoringController',
            'App\Http\Controllers\Api\V1\Tenant\AiTaskController',
            'App\Http\Controllers\Api\V1\Tenant\AiWorkflowController',
        ];

        foreach ($controllers as $class) {
            $this->assertTrue(
                class_exists($class),
                "Controller {$class} deve existir"
            );
        }
    }
}
