<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;
use App\Services\Auth\TenantUserDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantUserDirectoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $status): Tenant
    {
        return Tenant::create([
            'name' => 'Tenant '.$status,
            'slug' => 'tenant-'.$status.'-'.Str::lower(Str::random(6)),
            'status' => $status,
            'admin_name' => 'Admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'Password123',
        ]);
    }

    private function directoryEntry(Tenant $tenant, string $email): TenantUserDirectory
    {
        return TenantUserDirectory::create([
            'tenant_id' => (string) $tenant->getKey(),
            'tenant_user_id' => (string) Str::uuid(),
            'email_normalized' => $email,
            'user_name' => 'User',
            'active' => true,
        ]);
    }

    public function test_candidates_include_suspended_and_under_review_tenants(): void
    {
        $email = 'broker@example.com';

        $active = $this->makeTenant(Tenant::STATUS_ACTIVE);
        $suspended = $this->makeTenant(Tenant::STATUS_SUSPENDED);
        $underReview = $this->makeTenant(Tenant::STATUS_UNDER_REVIEW);
        $cancelled = $this->makeTenant(Tenant::STATUS_CANCELLED);
        $pending = $this->makeTenant(Tenant::STATUS_PENDING);

        foreach ([$active, $suspended, $underReview, $cancelled, $pending] as $tenant) {
            $this->directoryEntry($tenant, $email);
        }

        $candidates = app(TenantUserDirectoryService::class)->candidatesForEmail($email);
        $tenantIds = $candidates->pluck('tenant_id')->map(fn ($id) => (string) $id)->all();

        $this->assertContains((string) $active->getKey(), $tenantIds);
        $this->assertContains((string) $suspended->getKey(), $tenantIds);
        $this->assertContains((string) $underReview->getKey(), $tenantIds);
        $this->assertNotContains((string) $cancelled->getKey(), $tenantIds);
        $this->assertNotContains((string) $pending->getKey(), $tenantIds);
    }
}
