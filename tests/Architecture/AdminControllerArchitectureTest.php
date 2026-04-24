<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class AdminControllerArchitectureTest extends TestCase
{
    public function test_admin_user_controller_avoids_inline_validation_and_direct_queries(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Admin/UserController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('->validate(', $contents);
        $this->assertStringNotContainsString('User::query(', $contents);
        $this->assertStringNotContainsString('User::create(', $contents);
        $this->assertStringNotContainsString('findOrFail(', $contents);
    }

    public function test_admin_tenant_controller_avoids_inline_queries(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Admin/TenantController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('->validate(', $contents);
        $this->assertStringNotContainsString('Tenant::query(', $contents);
        $this->assertStringNotContainsString('Tenant::findOrFail(', $contents);
        $this->assertStringNotContainsString('User::count(', $contents);
        $this->assertStringNotContainsString('Terreno::count(', $contents);
        $this->assertStringNotContainsString('Produto::count(', $contents);
    }

    public function test_admin_post_controller_avoids_inline_validation_and_direct_queries(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Admin/PostController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('->validate(', $contents);
        $this->assertStringNotContainsString('Post::query(', $contents);
        $this->assertStringNotContainsString('Post::create(', $contents);
    }

    public function test_plan_swap_controller_avoids_inline_admin_checks(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Tenant/PlanSwapController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('abort_unless(', $contents);
        $this->assertStringNotContainsString('isAdmin(', $contents);
        $this->assertStringNotContainsString('Plan::where(', $contents);
        $this->assertStringNotContainsString('Plan::query(', $contents);
    }

    public function test_plan_swap_request_uses_real_authorization(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Requests/Tenant/PlanSwapRequest.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('return true;', $contents);
    }

    public function test_terreno_controller_avoids_inline_validation_and_direct_queries(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Tenant/TerrenoController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('->validate(', $contents);
        $this->assertStringNotContainsString('Terreno::query(', $contents);
        $this->assertStringNotContainsString('Terreno::create(', $contents);
        $this->assertStringNotContainsString('Terreno::find(', $contents);
    }

    public function test_terreno_workflow_controller_avoids_inline_validation_and_direct_queries(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Tenant/TerrenoWorkflowController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('->validate(', $contents);
        $this->assertStringNotContainsString('Terreno::find(', $contents);
        $this->assertStringNotContainsString('Gate::authorize(', $contents);
    }

    public function test_viabilidade_controller_avoids_inline_validation_and_direct_queries(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Tenant/ViabilidadeController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('->validate(', $contents);
        $this->assertStringNotContainsString('Viabilidade::find(', $contents);
        $this->assertStringNotContainsString('Viabilidade::withTrashed(', $contents);
        $this->assertStringNotContainsString('Gate::authorize(', $contents);
    }

    public function test_committee_controller_avoids_inline_validation_and_direct_queries(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Tenant/CommitteeController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('->validate(', $contents);
        $this->assertStringNotContainsString('ComiteRevisao::find(', $contents);
        $this->assertStringNotContainsString('Gate::authorize(', $contents);
    }

    public function test_negotiation_controller_avoids_inline_validation_and_direct_queries(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Tenant/NegotiationController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('->validate(', $contents);
        $this->assertStringNotContainsString('Negociacao::find(', $contents);
        $this->assertStringNotContainsString('Negociacao::with(', $contents);
        $this->assertStringNotContainsString('Gate::authorize(', $contents);
    }

    public function test_contract_controller_avoids_inline_validation_and_direct_queries(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Tenant/ContractController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('->validate(', $contents);
        $this->assertStringNotContainsString('Contrato::query(', $contents);
        $this->assertStringNotContainsString('Contrato::with(', $contents);
        $this->assertStringNotContainsString('Gate::authorize(', $contents);
    }

    public function test_legalizacao_controller_avoids_inline_validation_and_direct_queries(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Tenant/LegalizacaoController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('->validate(', $contents);
        $this->assertStringNotContainsString('Legalizacao::find(', $contents);
        $this->assertStringNotContainsString('Gate::authorize(', $contents);
    }

    public function test_legalizacao_etapa_controller_avoids_inline_validation_and_direct_queries(): void
    {
        $contents = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Tenant/LegalizacaoEtapaController.php');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('->validate(', $contents);
        $this->assertStringNotContainsString('Gate::authorize(', $contents);
        $this->assertStringNotContainsString('LegalizacaoEtapa::find(', $contents);
        $this->assertStringNotContainsString('LegalizacaoEtapa::query(', $contents);
        $this->assertStringNotContainsString('LegalizacaoEtapa::where(', $contents);
        $this->assertStringNotContainsString('LegalizacaoEtapa::create(', $contents);
        $this->assertStringNotContainsString('LegalizacaoEtapa::with(', $contents);
    }

    public function test_legalizacao_etapa_requests_use_real_authorization(): void
    {
        $requests = [
            __DIR__.'/../../app/Http/Requests/Tenant/ListLegalizacaoEtapasRequest.php',
            __DIR__.'/../../app/Http/Requests/Tenant/ShowLegalizacaoEtapaRequest.php',
            __DIR__.'/../../app/Http/Requests/Tenant/DestroyLegalizacaoEtapaRequest.php',
            __DIR__.'/../../app/Http/Requests/Tenant/ReorderEtapasRequest.php',
            __DIR__.'/../../app/Http/Requests/Tenant/UpdateStatusEtapaRequest.php',
        ];

        foreach ($requests as $path) {
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $this->assertStringNotContainsString('return true;', $contents, "{$path} should not have authorize() returning true");
        }
    }

    public function test_tenant_admin_people_controllers_avoid_direct_queries(): void
    {
        $controllers = [
            __DIR__.'/../../app/Http/Controllers/Api/V1/Tenant/Admin/DepartmentController.php',
            __DIR__.'/../../app/Http/Controllers/Api/V1/Tenant/Admin/PositionController.php',
            __DIR__.'/../../app/Http/Controllers/Api/V1/Tenant/Admin/UserManagementController.php',
        ];

        foreach ($controllers as $path) {
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $this->assertStringNotContainsString('::find(', $contents, "{$path} should not use direct find queries");
            $this->assertStringNotContainsString('::with(', $contents, "{$path} should not use direct eager-load queries");
            $this->assertStringNotContainsString('::query(', $contents, "{$path} should not use direct query builders");
        }
    }
}
