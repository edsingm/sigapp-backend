<?php

namespace Tests\Unit\TenantPlan;

use App\Enums\Common\EntitlementType;
use App\Http\Resources\EntitlementResource;
use App\Http\Resources\TenantEntitlementResource;
use App\Models\Central\Entitlement;
use App\Models\Central\TenantEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantEntitlementResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecord(): TenantEntitlement
    {
        $entitlement = Entitlement::create([
            'key'           => 'extra.users',
            'type'          => EntitlementType::LIMIT->value,
            'label'         => 'Extra Users',
            'default_value' => 0,
        ]);

        $tenant = \App\Models\Central\Tenant::create([
            'name'           => 'Resource Test Tenant',
            'slug'           => 'resource-test',
            'status'         => \App\Models\Central\Tenant::STATUS_ACTIVE,
            'admin_name'     => 'Admin',
            'admin_email'    => 'admin@resource-test.com',
            'admin_password' => 'password',
        ]);

        return TenantEntitlement::create([
            'tenant_id'      => $tenant->id,
            'entitlement_id' => $entitlement->id,
            'value'          => 50,
            'price'          => 9900,
        ]);
    }

    public function test_it_includes_all_expected_fields(): void
    {
        $record  = $this->makeRecord();
        $payload = (new TenantEntitlementResource($record))->resolve();

        foreach (['id', 'entitlement_id', 'value', 'price', 'price_formatted', 'created_at', 'updated_at'] as $field) {
            $this->assertArrayHasKey($field, $payload, "Field [{$field}] missing from TenantEntitlementResource");
        }
    }

    public function test_price_is_formatted_as_brl(): void
    {
        $record  = $this->makeRecord();
        $payload = (new TenantEntitlementResource($record))->resolve();

        $this->assertSame('R$ 99,00', $payload['price_formatted']);
    }

    public function test_price_raw_is_in_cents(): void
    {
        $record  = $this->makeRecord();
        $payload = (new TenantEntitlementResource($record))->resolve();

        $this->assertSame(9900, $payload['price']);
    }

    public function test_entitlement_relation_is_absent_when_not_loaded(): void
    {
        $record  = $this->makeRecord();
        $payload = (new TenantEntitlementResource($record))->resolve();

        // whenLoaded returns MissingValue when not loaded — resolve strips it from array
        $this->assertArrayNotHasKey('entitlement', $payload);
    }

    public function test_entitlement_relation_is_present_when_loaded(): void
    {
        $record = $this->makeRecord();
        $record->load('entitlement');

        $payload = (new TenantEntitlementResource($record))->resolve();

        $this->assertArrayHasKey('entitlement', $payload);
        $this->assertSame('extra.users', $payload['entitlement']['key']);
    }

    public function test_it_does_not_expose_raw_tenant_id(): void
    {
        $record  = $this->makeRecord();
        $payload = (new TenantEntitlementResource($record))->resolve();

        // tenant_id is an internal join key, should not be directly exposed in the resource
        $this->assertArrayNotHasKey('tenant_id', $payload);
    }
}
