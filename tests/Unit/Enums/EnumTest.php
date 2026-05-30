<?php

namespace Tests\Unit\Enums;

use App\Enums\AccessLevel;
use App\Enums\Common\EntitlementType;
use App\Enums\Common\RolesEnum;
use App\Enums\Common\SectorsEnum;
use App\Enums\Common\SubmodulesEnum;
use App\Enums\LegalizacaoEtapaStatus;
use App\Enums\LegalizacaoStatus;
use App\Enums\PerfilFinanciamento;
use App\Enums\ProjetoStatus;
use App\Enums\TenantStatus;
use App\Enums\UserType;
use App\Enums\WorkflowStatus;
use Tests\TestCase;

class EnumTest extends TestCase
{
    // -----------------------------------------------------------------
    // TenantStatus
    // -----------------------------------------------------------------

    public function test_tenant_status_tem_todos_os_casos(): void
    {
        $cases = TenantStatus::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(TenantStatus::PENDING, $cases);
        $this->assertContains(TenantStatus::ACTIVE, $cases);
        $this->assertContains(TenantStatus::SUSPENDED, $cases);
        $this->assertContains(TenantStatus::CANCELLED, $cases);
        $this->assertContains(TenantStatus::SETUP_FAILED, $cases);
    }

    public function test_tenant_status_values(): void
    {
        $this->assertSame('pending', TenantStatus::PENDING->value);
        $this->assertSame('active', TenantStatus::ACTIVE->value);
        $this->assertSame('suspended', TenantStatus::SUSPENDED->value);
        $this->assertSame('cancelled', TenantStatus::CANCELLED->value);
        $this->assertSame('setup_failed', TenantStatus::SETUP_FAILED->value);
    }

    public function test_tenant_status_label_retorna_string(): void
    {
        foreach (TenantStatus::cases() as $case) {
            $this->assertNotEmpty($case->label());
        }
    }

    // -----------------------------------------------------------------
    // AccessLevel
    // -----------------------------------------------------------------

    public function test_access_level_tem_casos(): void
    {
        $cases = AccessLevel::cases();

        $this->assertNotEmpty($cases);
    }

    public function test_access_level_from_aceita_valores_validos(): void
    {
        foreach (AccessLevel::cases() as $case) {
            $this->assertSame($case, AccessLevel::from($case->value));
        }
    }

    // -----------------------------------------------------------------
    // EntitlementType
    // -----------------------------------------------------------------

    public function test_entitlement_type_tem_casos(): void
    {
        $cases = EntitlementType::cases();

        $this->assertNotEmpty($cases);
    }

    public function test_entitlement_type_from_aceita_valores_validos(): void
    {
        foreach (EntitlementType::cases() as $case) {
            $this->assertSame($case, EntitlementType::from($case->value));
        }
    }

    // -----------------------------------------------------------------
    // RolesEnum
    // -----------------------------------------------------------------

    public function test_roles_enum_tem_casos(): void
    {
        $cases = RolesEnum::cases();

        $this->assertNotEmpty($cases);
    }

    public function test_roles_enum_from_aceita_valores_validos(): void
    {
        foreach (RolesEnum::cases() as $case) {
            $this->assertSame($case, RolesEnum::from($case->value));
        }
    }

    // -----------------------------------------------------------------
    // SectorsEnum
    // -----------------------------------------------------------------

    public function test_sectors_enum_tem_casos(): void
    {
        $cases = SectorsEnum::cases();

        $this->assertNotEmpty($cases);
    }

    public function test_sectors_enum_from_aceita_valores_validos(): void
    {
        foreach (SectorsEnum::cases() as $case) {
            $this->assertSame($case, SectorsEnum::from($case->value));
        }
    }

    // -----------------------------------------------------------------
    // SubmodulesEnum
    // -----------------------------------------------------------------

    public function test_submodules_enum_tem_casos(): void
    {
        $cases = SubmodulesEnum::cases();

        $this->assertNotEmpty($cases);
    }

    public function test_submodules_enum_from_aceita_valores_validos(): void
    {
        foreach (SubmodulesEnum::cases() as $case) {
            $this->assertSame($case, SubmodulesEnum::from($case->value));
        }
    }

    // -----------------------------------------------------------------
    // LegalizacaoEtapaStatus
    // -----------------------------------------------------------------

    public function test_legalizacao_etapa_status_tem_casos(): void
    {
        $cases = LegalizacaoEtapaStatus::cases();

        $this->assertNotEmpty($cases);
    }

    public function test_legalizacao_etapa_status_from_aceita_valores_validos(): void
    {
        foreach (LegalizacaoEtapaStatus::cases() as $case) {
            $this->assertSame($case, LegalizacaoEtapaStatus::from($case->value));
        }
    }

    // -----------------------------------------------------------------
    // LegalizacaoStatus
    // -----------------------------------------------------------------

    public function test_legalizacao_status_tem_casos(): void
    {
        $cases = LegalizacaoStatus::cases();

        $this->assertNotEmpty($cases);
    }

    public function test_legalizacao_status_from_aceita_valores_validos(): void
    {
        foreach (LegalizacaoStatus::cases() as $case) {
            $this->assertSame($case, LegalizacaoStatus::from($case->value));
        }
    }

    // -----------------------------------------------------------------
    // PerfilFinanciamento
    // -----------------------------------------------------------------

    public function test_perfil_financiamento_tem_casos(): void
    {
        $cases = PerfilFinanciamento::cases();

        $this->assertNotEmpty($cases);
    }

    public function test_perfil_financiamento_from_aceita_valores_validos(): void
    {
        foreach (PerfilFinanciamento::cases() as $case) {
            $this->assertSame($case, PerfilFinanciamento::from($case->value));
        }
    }

    // -----------------------------------------------------------------
    // ProjetoStatus
    // -----------------------------------------------------------------

    public function test_projeto_status_tem_casos(): void
    {
        $cases = ProjetoStatus::cases();

        $this->assertNotEmpty($cases);
    }

    public function test_projeto_status_from_aceita_valores_validos(): void
    {
        foreach (ProjetoStatus::cases() as $case) {
            $this->assertSame($case, ProjetoStatus::from($case->value));
        }
    }

    // -----------------------------------------------------------------
    // UserType
    // -----------------------------------------------------------------

    public function test_user_type_tem_casos(): void
    {
        $cases = UserType::cases();

        $this->assertNotEmpty($cases);
    }

    public function test_user_type_from_aceita_valores_validos(): void
    {
        foreach (UserType::cases() as $case) {
            $this->assertSame($case, UserType::from($case->value));
        }
    }

    // -----------------------------------------------------------------
    // WorkflowStatus
    // -----------------------------------------------------------------

    public function test_workflow_status_tem_casos(): void
    {
        $cases = WorkflowStatus::cases();

        $this->assertNotEmpty($cases);
    }

    public function test_workflow_status_from_aceita_valores_validos(): void
    {
        foreach (WorkflowStatus::cases() as $case) {
            $this->assertSame($case, WorkflowStatus::from($case->value));
        }
    }

    // -----------------------------------------------------------------
    // tryFrom rejeita valores inválidos
    // -----------------------------------------------------------------

    public function test_tryfrom_rejeita_valores_invalidos(): void
    {
        $invalidValue = $this->invalidEnumValue();

        $this->assertNull(TenantStatus::tryFrom($invalidValue));
        $this->assertNull(AccessLevel::tryFrom($invalidValue));
        $this->assertNull(EntitlementType::tryFrom($invalidValue));
        $this->assertNull(RolesEnum::tryFrom($invalidValue));
        $this->assertNull(SectorsEnum::tryFrom($invalidValue));
        $this->assertNull(SubmodulesEnum::tryFrom($invalidValue));
        $this->assertNull(LegalizacaoEtapaStatus::tryFrom($invalidValue));
        $this->assertNull(LegalizacaoStatus::tryFrom($invalidValue));
        $this->assertNull(PerfilFinanciamento::tryFrom($invalidValue));
        $this->assertNull(ProjetoStatus::tryFrom($invalidValue));
        $this->assertNull(UserType::tryFrom($invalidValue));
        $this->assertNull(WorkflowStatus::tryFrom($invalidValue));
    }

    private function invalidEnumValue(): string
    {
        return '__invalid_enum_value__';
    }
}
