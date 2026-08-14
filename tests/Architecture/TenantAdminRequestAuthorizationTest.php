<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class TenantAdminRequestAuthorizationTest extends TestCase
{
    private const ROOT_PATH = __DIR__.'/../../';

    /**
     * @return array<int, string>
     */
    public static function requestFiles(): array
    {
        return [
            self::ROOT_PATH.'app/Http/Requests/Tenant/StoreDepartmentRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/UpdateDepartmentRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/StorePermissionRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/UpdatePermissionRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/StoreRoleRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/UpdateRoleRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/StoreUserRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/UpdateUserRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/UpdateUserModulePermissionsRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/StorePremissasViabilidadeRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/UpdatePremissasViabilidadeRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/StoreVeiculoRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/UpdateVeiculoRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/SalvarTermoDeUsoVersaoRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/ShowTenantBillingProfileRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/UpdateTenantBillingProfileRequest.php',
        ];
    }

    public function test_tenant_admin_requests_do_not_use_trivial_authorization(): void
    {
        foreach (self::requestFiles() as $file) {
            $contents = file_get_contents($file);

            $this->assertIsString($contents);
            $this->assertStringNotContainsString('return true;', $contents, "Trivial authorization found in {$file}");
        }
    }

    public function test_authenticated_tenant_requests_do_not_use_trivial_authorization(): void
    {
        $requests = [
            self::ROOT_PATH.'app/Http/Requests/Tenant/StoreRequisicaoVeiculoRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/StoreLegalAcceptanceRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/AuthorizeTenantPrivacyRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/StorePrivacyErasureRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/AnonymizeProprietarioRequest.php',
            self::ROOT_PATH.'app/Http/Requests/Tenant/AuthorizeTenantDirectorRequest.php',
        ];

        foreach ($requests as $file) {
            $contents = file_get_contents($file);

            $this->assertIsString($contents);
            $this->assertStringNotContainsString('return true;', $contents, "Trivial authorization found in {$file}");
        }
    }
}
